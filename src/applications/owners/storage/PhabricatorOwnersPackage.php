<?php

final class PhabricatorOwnersPackage
  extends PhabricatorOwnersDAO
  implements
    PhabricatorPolicyInterface,
    PhabricatorApplicationTransactionInterface,
    PhabricatorCustomFieldInterface,
    PhabricatorDestructibleInterface,
    PhabricatorConduitResultInterface,
    PhabricatorFulltextInterface,
    PhabricatorNgramsInterface {

  protected $name;
  protected $originalName;
  protected $auditingEnabled;
  protected $description;
  protected $primaryOwnerPHID;
  protected $mailKey;
  protected $status;
  protected $viewPolicy;
  protected $editPolicy;

  private $paths = self::ATTACHABLE;
  private $owners = self::ATTACHABLE;
  private $customFields = self::ATTACHABLE;

  const STATUS_ACTIVE = 'active';
  const STATUS_ARCHIVED = 'archived';

  public static function initializeNewPackage(PhabricatorUser $actor) {
    $app = id(new PhabricatorApplicationQuery())
      ->setViewer($actor)
      ->withClasses(array('PhabricatorOwnersApplication'))
      ->executeOne();

    $view_policy = $app->getPolicy(
      PhabricatorOwnersDefaultViewCapability::CAPABILITY);
    $edit_policy = $app->getPolicy(
      PhabricatorOwnersDefaultEditCapability::CAPABILITY);

    return id(new PhabricatorOwnersPackage())
      ->setAuditingEnabled(0)
      ->setViewPolicy($view_policy)
      ->setEditPolicy($edit_policy)
      ->attachPaths(array())
      ->setStatus(self::STATUS_ACTIVE)
      ->attachOwners(array())
      ->setDescription('');
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
        'name' => 'sort128',
        'originalName' => 'text255',
        'description' => 'text',
        'primaryOwnerPHID' => 'phid?',
        'auditingEnabled' => 'bool',
        'mailKey' => 'bytes20',
        'status' => 'text32',
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

  public function getOwnerPHIDs() {
    return mpull($this->getOwners(), 'getUserPHID');
  }

  public function isOwnerPHID($phid) {
    if (!$phid) {
      return false;
    }

    $owner_phids = $this->getOwnerPHIDs();
    $owner_phids = array_fuse($owner_phids);

    return isset($owner_phids[$phid]);
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicyCapability::CAN_EDIT,
    );
  }

  public function getPolicy($capability) {
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        return $this->getViewPolicy();
      case PhabricatorPolicyCapability::CAN_EDIT:
        return $this->getEditPolicy();
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        if ($this->isOwnerPHID($viewer->getPHID())) {
          return true;
        }
        break;
    }

    return false;
  }

  public function describeAutomaticCapability($capability) {
    return pht('Owners of a package may always view it.');
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


/* -(  PhabricatorDestructibleInterface  )----------------------------------- */


  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine) {

    $this->openTransaction();
      $conn_w = $this->establishConnection('w');

      queryfx(
        $conn_w,
        'DELETE FROM %T WHERE packageID = %d',
        id(new PhabricatorOwnersPath())->getTableName(),
        $this->getID());

      queryfx(
        $conn_w,
        'DELETE FROM %T WHERE packageID = %d',
        id(new PhabricatorOwnersOwner())->getTableName(),
        $this->getID());

      $this->delete();
    $this->saveTransaction();
  }


/* -(  PhabricatorConduitResultInterface  )---------------------------------- */


  public function getFieldSpecificationsForConduit() {
    return array(
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('name')
        ->setType('string')
        ->setDescription(pht('The name of the package.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('description')
        ->setType('string')
        ->setDescription(pht('The package description.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('status')
        ->setType('string')
        ->setDescription(pht('Active or archived status of the package.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('owners')
        ->setType('list<map<string, wild>>')
        ->setDescription(pht('List of package owners.')),
    );
  }

  public function getFieldValuesForConduit() {
    $owner_list = array();
    foreach ($this->getOwners() as $owner) {
      $owner_list[] = array(
        'ownerPHID' => $owner->getUserPHID(),
      );
    }

    return array(
      'name' => $this->getName(),
      'description' => $this->getDescription(),
      'status' => $this->getStatus(),
      'owners' => $owner_list,
    );
  }

  public function getConduitSearchAttachments() {
    return array(
      id(new PhabricatorOwnersPathsSearchEngineAttachment())
        ->setAttachmentKey('paths'),
    );
  }


/* -(  PhabricatorFulltextInterface  )--------------------------------------- */


  public function newFulltextEngine() {
    return new PhabricatorOwnersPackageFulltextEngine();
  }


/* -(  PhabricatorNgramsInterface  )----------------------------------------- */


  public function newNgrams() {
    return array(
      id(new PhabricatorOwnersPackageNameNgrams())
        ->setValue($this->getName()),
    );
  }

}
