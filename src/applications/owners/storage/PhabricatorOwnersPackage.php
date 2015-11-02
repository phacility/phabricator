<?php

final class PhabricatorOwnersPackage
  extends PhabricatorOwnersDAO
  implements
    PhabricatorPolicyInterface,
    PhabricatorApplicationTransactionInterface,
    PhabricatorCustomFieldInterface {

  protected $name;
  protected $originalName;
  protected $auditingEnabled;
  protected $description;
  protected $primaryOwnerPHID;
  protected $mailKey;
  protected $status;

  private $paths = self::ATTACHABLE;
  private $owners = self::ATTACHABLE;
  private $customFields = self::ATTACHABLE;

  const STATUS_ACTIVE = 'active';
  const STATUS_ARCHIVED = 'archived';

  public static function initializeNewPackage(PhabricatorUser $actor) {
    return id(new PhabricatorOwnersPackage())
      ->setAuditingEnabled(0)
      ->attachPaths(array())
      ->setStatus(self::STATUS_ACTIVE)
      ->attachOwners(array());
  }

  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
    );
  }

  public function getPolicy($capability) {
    return PhabricatorPolicies::POLICY_USER;
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return false;
  }

  public function describeAutomaticCapability($capability) {
    return null;
  }

  public static function getStatusNameMap() {
    return array(
      self::STATUS_ACTIVE => pht('Active'),
      self::STATUS_ARCHIVED => pht('Archived'),
    );
  }

  protected function getConfiguration() {
    return array(
      // This information is better available from the history table.
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_COLUMN_SCHEMA => array(
        'name' => 'text128',
        'originalName' => 'text255',
        'description' => 'text',
        'primaryOwnerPHID' => 'phid?',
        'auditingEnabled' => 'bool',
        'mailKey' => 'bytes20',
        'status' => 'text32',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_phid' => null,
        'phid' => array(
          'columns' => array('phid'),
          'unique' => true,
        ),
        'name' => array(
          'columns' => array('name'),
          'unique' => true,
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorOwnersPackagePHIDType::TYPECONST);
  }

  public function save() {
    if (!$this->getMailKey()) {
      $this->setMailKey(Filesystem::readRandomCharacters(20));
    }

    return parent::save();
  }

  public function isArchived() {
    return ($this->getStatus() == self::STATUS_ARCHIVED);
  }

  public function setName($name) {
    $this->name = $name;
    if (!$this->getID()) {
      $this->originalName = $name;
    }
    return $this;
  }

  public function loadOwners() {
    if (!$this->getID()) {
      return array();
    }
    return id(new PhabricatorOwnersOwner())->loadAllWhere(
      'packageID = %d',
      $this->getID());
  }

  public function loadPaths() {
    if (!$this->getID()) {
      return array();
    }
    return id(new PhabricatorOwnersPath())->loadAllWhere(
      'packageID = %d',
      $this->getID());
  }

  public static function loadAffectedPackages(
    PhabricatorRepository $repository,
    array $paths) {

    if (!$paths) {
      return array();
    }

    return self::loadPackagesForPaths($repository, $paths);
  }

  public static function loadOwningPackages($repository, $path) {
    if (empty($path)) {
      return array();
    }

    return self::loadPackagesForPaths($repository, array($path), 1);
  }

  private static function loadPackagesForPaths(
    PhabricatorRepository $repository,
    array $paths,
    $limit = 0) {

    $fragments = array();
    foreach ($paths as $path) {
      foreach (self::splitPath($path) as $fragment) {
        $fragments[$fragment][$path] = true;
      }
    }

    $package = new PhabricatorOwnersPackage();
    $path = new PhabricatorOwnersPath();
    $conn = $package->establishConnection('r');

    $repository_clause = qsprintf(
      $conn,
      'AND p.repositoryPHID = %s',
      $repository->getPHID());

    // NOTE: The list of $paths may be very large if we're coming from
    // the OwnersWorker and processing, e.g., an SVN commit which created a new
    // branch. Break it apart so that it will fit within 'max_allowed_packet',
    // and then merge results in PHP.

    $rows = array();
    foreach (array_chunk(array_keys($fragments), 128) as $chunk) {
      $rows[] = queryfx_all(
        $conn,
        'SELECT pkg.id, p.excluded, p.path
          FROM %T pkg JOIN %T p ON p.packageID = pkg.id
          WHERE p.path IN (%Ls) %Q',
        $package->getTableName(),
        $path->getTableName(),
        $chunk,
        $repository_clause);
    }
    $rows = array_mergev($rows);

    $ids = self::findLongestPathsPerPackage($rows, $fragments);

    if (!$ids) {
      return array();
    }

    arsort($ids);
    if ($limit) {
      $ids = array_slice($ids, 0, $limit, $preserve_keys = true);
    }
    $ids = array_keys($ids);

    $packages = $package->loadAllWhere('id in (%Ld)', $ids);
    $packages = array_select_keys($packages, $ids);

    return $packages;
  }

  public static function loadPackagesForRepository($repository) {
    $package = new PhabricatorOwnersPackage();
    $ids = ipull(
      queryfx_all(
        $package->establishConnection('r'),
        'SELECT DISTINCT packageID FROM %T WHERE repositoryPHID = %s',
        id(new PhabricatorOwnersPath())->getTableName(),
        $repository->getPHID()),
      'packageID');

    return $package->loadAllWhere('id in (%Ld)', $ids);
  }

  public static function findLongestPathsPerPackage(array $rows, array $paths) {
    $ids = array();

    foreach (igroup($rows, 'id') as $id => $package_paths) {
      $relevant_paths = array_select_keys(
        $paths,
        ipull($package_paths, 'path'));

      // For every package, remove all excluded paths.
      $remove = array();
      foreach ($package_paths as $package_path) {
        if ($package_path['excluded']) {
          $remove += idx($relevant_paths, $package_path['path'], array());
          unset($relevant_paths[$package_path['path']]);
        }
      }

      if ($remove) {
        foreach ($relevant_paths as $fragment => $fragment_paths) {
          $relevant_paths[$fragment] = array_diff_key($fragment_paths, $remove);
        }
      }

      $relevant_paths = array_filter($relevant_paths);
      if ($relevant_paths) {
        $ids[$id] = max(array_map('strlen', array_keys($relevant_paths)));
      }
    }

    return $ids;
  }

  public static function splitPath($path) {
    $trailing_slash = preg_match('@/$@', $path) ? '/' : '';
    $path = trim($path, '/');
    $parts = explode('/', $path);

    $result = array();
    while (count($parts)) {
      $result[] = '/'.implode('/', $parts).$trailing_slash;
      $trailing_slash = '/';
      array_pop($parts);
    }
    $result[] = '/';

    return array_reverse($result);
  }

  public function attachPaths(array $paths) {
    assert_instances_of($paths, 'PhabricatorOwnersPath');
    $this->paths = $paths;
    return $this;
  }

  public function getPaths() {
    return $this->assertAttached($this->paths);
  }

  public function attachOwners(array $owners) {
    assert_instances_of($owners, 'PhabricatorOwnersOwner');
    $this->owners = $owners;
    return $this;
  }

  public function getOwners() {
    return $this->assertAttached($this->owners);
  }


/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new PhabricatorOwnersPackageTransactionEditor();
  }

  public function getApplicationTransactionObject() {
    return $this;
  }

  public function getApplicationTransactionTemplate() {
    return new PhabricatorOwnersPackageTransaction();
  }

  public function willRenderTimeline(
    PhabricatorApplicationTransactionView $timeline,
    AphrontRequest $request) {
    return $timeline;
  }


/* -(  PhabricatorCustomFieldInterface  )------------------------------------ */


  public function getCustomFieldSpecificationForRole($role) {
    return PhabricatorEnv::getEnvConfig('owners.fields');
  }

  public function getCustomFieldBaseClass() {
    return 'PhabricatorOwnersCustomField';
  }

  public function getCustomFields() {
    return $this->assertAttached($this->customFields);
  }

  public function attachCustomFields(PhabricatorCustomFieldAttachment $fields) {
    $this->customFields = $fields;
    return $this;
  }

}
