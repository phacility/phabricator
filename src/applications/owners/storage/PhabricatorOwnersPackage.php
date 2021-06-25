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
    PhabricatorFerretInterface,
    PhabricatorNgramsInterface {

  protected $name;
  protected $autoReview;
  protected $description;
  protected $status;
  protected $viewPolicy;
  protected $editPolicy;
  protected $dominion;
  protected $properties = array();
  protected $auditingState;
  protected $authorityMode;

  private $paths = self::ATTACHABLE;
  private $owners = self::ATTACHABLE;
  private $customFields = self::ATTACHABLE;
  private $pathRepositoryMap = array();

  const STATUS_ACTIVE = 'active';
  const STATUS_ARCHIVED = 'archived';

  const AUTOREVIEW_NONE = 'none';
  const AUTOREVIEW_SUBSCRIBE = 'subscribe';
  const AUTOREVIEW_SUBSCRIBE_ALWAYS = 'subscribe-always';
  const AUTOREVIEW_REVIEW = 'review';
  const AUTOREVIEW_REVIEW_ALWAYS = 'review-always';
  const AUTOREVIEW_BLOCK = 'block';
  const AUTOREVIEW_BLOCK_ALWAYS = 'block-always';

  const DOMINION_STRONG = 'strong';
  const DOMINION_WEAK = 'weak';

  const AUTHORITY_STRONG = 'strong';
  const AUTHORITY_WEAK = 'weak';

  const PROPERTY_IGNORED = 'ignored';

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
      ->setAuditingState(PhabricatorOwnersAuditRule::AUDITING_NONE)
      ->setAutoReview(self::AUTOREVIEW_NONE)
      ->setDominion(self::DOMINION_STRONG)
      ->setAuthorityMode(self::AUTHORITY_STRONG)
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

  public static function getAutoreviewOptionsMap() {
    return array(
      self::AUTOREVIEW_NONE => array(
        'name' => pht('No Autoreview'),
      ),
      self::AUTOREVIEW_REVIEW => array(
        'name' => pht('Review Changes With Non-Owner Author'),
        'authority' => true,
      ),
      self::AUTOREVIEW_BLOCK => array(
        'name' => pht('Review Changes With Non-Owner Author (Blocking)'),
        'authority' => true,
      ),
      self::AUTOREVIEW_SUBSCRIBE => array(
        'name' => pht('Subscribe to Changes With Non-Owner Author'),
        'authority' => true,
      ),
      self::AUTOREVIEW_REVIEW_ALWAYS => array(
        'name' => pht('Review All Changes'),
      ),
      self::AUTOREVIEW_BLOCK_ALWAYS => array(
        'name' => pht('Review All Changes (Blocking)'),
      ),
      self::AUTOREVIEW_SUBSCRIBE_ALWAYS => array(
        'name' => pht('Subscribe to All Changes'),
      ),
    );
  }

  public static function getDominionOptionsMap() {
    return array(
      self::DOMINION_STRONG => array(
        'name' => pht('Strong (Control All Paths)'),
        'short' => pht('Strong'),
      ),
      self::DOMINION_WEAK => array(
        'name' => pht('Weak (Control Unowned Paths)'),
        'short' => pht('Weak'),
      ),
    );
  }

  public static function getAuthorityOptionsMap() {
    return array(
      self::AUTHORITY_STRONG => array(
        'name' => pht('Strong (Package Owns Paths)'),
        'short' => pht('Strong'),
      ),
      self::AUTHORITY_WEAK => array(
        'name' => pht('Weak (Package Watches Paths)'),
        'short' => pht('Weak'),
      ),
    );
  }

  protected function getConfiguration() {
    return array(
      // This information is better available from the history table.
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'properties' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'name' => 'sort',
        'description' => 'text',
        'auditingState' => 'text32',
        'status' => 'text32',
        'autoReview' => 'text32',
        'dominion' => 'text32',
        'authorityMode' => 'text32',
      ),
    ) + parent::getConfiguration();
  }

  public function getPHIDType() {
    return PhabricatorOwnersPackagePHIDType::TYPECONST;
  }

  public function isArchived() {
    return ($this->getStatus() == self::STATUS_ARCHIVED);
  }

  public function getMustMatchUngeneratedPaths() {
    $ignore_attributes = $this->getIgnoredPathAttributes();
    return !empty($ignore_attributes['generated']);
  }

  public function getPackageProperty($key, $default = null) {
    return idx($this->properties, $key, $default);
  }

  public function setPackageProperty($key, $value) {
    $this->properties[$key] = $value;
    return $this;
  }

  public function getIgnoredPathAttributes() {
    return $this->getPackageProperty(self::PROPERTY_IGNORED, array());
  }

  public function setIgnoredPathAttributes(array $attributes) {
    return $this->setPackageProperty(self::PROPERTY_IGNORED, $attributes);
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

  public static function loadAffectedPackagesForChangesets(
    PhabricatorRepository $repository,
    DifferentialDiff $diff,
    array $changesets) {
    assert_instances_of($changesets, 'DifferentialChangeset');

    $paths_all = array();
    $paths_ungenerated = array();

    foreach ($changesets as $changeset) {
      $path = $changeset->getAbsoluteRepositoryPath($repository, $diff);

      $paths_all[] = $path;

      if (!$changeset->isGeneratedChangeset()) {
        $paths_ungenerated[] = $path;
      }
    }

    if (!$paths_all) {
      return array();
    }

    $packages_all = self::loadAffectedPackages(
      $repository,
      $paths_all);

    // If there are no generated changesets, we can't possibly need to throw
    // away any packages for matching only generated paths. Just return the
    // full set of packages.
    if ($paths_ungenerated === $paths_all) {
      return $packages_all;
    }

    $must_match_ungenerated = array();
    foreach ($packages_all as $package) {
      if ($package->getMustMatchUngeneratedPaths()) {
        $must_match_ungenerated[] = $package;
      }
    }

    // If no affected packages have the "Ignore Generated Paths" flag set, we
    // can't possibly need to throw any away.
    if (!$must_match_ungenerated) {
      return $packages_all;
    }

    if ($paths_ungenerated) {
      $packages_ungenerated = self::loadAffectedPackages(
        $repository,
        $paths_ungenerated);
    } else {
      $packages_ungenerated = array();
    }

    // We have some generated paths, and some packages that ignore generated
    // paths. Take all the packages which:
    //
    //   - ignore generated paths; and
    //   - didn't match any ungenerated paths
    //
    // ...and remove them from the list.

    $must_match_ungenerated = mpull($must_match_ungenerated, null, 'getID');
    $packages_ungenerated = mpull($packages_ungenerated, null, 'getID');
    $packages_all = mpull($packages_all, null, 'getID');

    foreach ($must_match_ungenerated as $package_id => $package) {
      if (!isset($packages_ungenerated[$package_id])) {
        unset($packages_all[$package_id]);
      }
    }

    return $packages_all;
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
    foreach (array_chunk(array_keys($fragments), 1024) as $chunk) {
      $indexes = array();
      foreach ($chunk as $fragment) {
        $indexes[] = PhabricatorHash::digestForIndex($fragment);
      }

      $rows[] = queryfx_all(
        $conn,
        'SELECT pkg.id, pkg.dominion, p.excluded, p.path
          FROM %T pkg JOIN %T p ON p.packageID = pkg.id
          WHERE p.pathIndex IN (%Ls) AND pkg.status IN (%Ls) %Q',
        $package->getTableName(),
        $path->getTableName(),
        $indexes,
        array(
          self::STATUS_ACTIVE,
        ),
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

    // Build a map from each path to all the package paths which match it.
    $path_hits = array();
    $weak = array();
    foreach ($rows as $row) {
      $id = $row['id'];
      $path = $row['path'];
      $length = strlen($path);
      $excluded = $row['excluded'];

      if ($row['dominion'] === self::DOMINION_WEAK) {
        $weak[$id] = true;
      }

      $matches = $paths[$path];
      foreach ($matches as $match => $ignored) {
        $path_hits[$match][] = array(
          'id' => $id,
          'excluded' => $excluded,
          'length' => $length,
        );
      }
    }

    // For each path, process the matching package paths to figure out which
    // packages actually own it.
    $path_packages = array();
    foreach ($path_hits as $match => $hits) {
      $hits = isort($hits, 'length');

      $packages = array();
      foreach ($hits as $hit) {
        $package_id = $hit['id'];
        if ($hit['excluded']) {
          unset($packages[$package_id]);
        } else {
          $packages[$package_id] = $hit;
        }
      }

      $path_packages[$match] = $packages;
    }

    // Remove packages with weak dominion rules that should cede control to
    // a more specific package.
    if ($weak) {
      foreach ($path_packages as $match => $packages) {

        // Group packages by length.
        $length_map = array();
        foreach ($packages as $package_id => $package) {
          $length_map[$package['length']][$package_id] = $package;
        }

        // For each path length, remove all weak packages if there are any
        // strong packages of the same length. This makes sure that if there
        // are one or more strong claims on a particular path, only those
        // claims stand.
        foreach ($length_map as $package_list) {
          $any_strong = false;
          foreach ($package_list as $package_id => $package) {
            if (!isset($weak[$package_id])) {
              $any_strong = true;
              break;
            }
          }

          if ($any_strong) {
            foreach ($package_list as $package_id => $package) {
              if (isset($weak[$package_id])) {
                unset($packages[$package_id]);
              }
            }
          }
        }

        $packages = isort($packages, 'length');
        $packages = array_reverse($packages, true);

        $best_length = null;
        foreach ($packages as $package_id => $package) {
          // If this is the first package we've encountered, note its length.
          // We're iterating over the packages from longest to shortest match,
          // so packages of this length always have the best claim on the path.
          if ($best_length === null) {
            $best_length = $package['length'];
          }

          // If this package has the same length as the best length, its claim
          // stands.
          if ($package['length'] === $best_length) {
            continue;
          }

          // If this is a weak package and does not have the best length,
          // cede its claim to the stronger package.
          if (isset($weak[$package_id])) {
            unset($packages[$package_id]);
          }
        }

        $path_packages[$match] = $packages;
      }
    }

    // For each package that owns at least one path, identify the longest
    // path it owns.
    $package_lengths = array();
    foreach ($path_packages as $match => $hits) {
      foreach ($hits as $hit) {
        $length = $hit['length'];
        $id = $hit['id'];
        if (empty($package_lengths[$id])) {
          $package_lengths[$id] = $length;
        } else {
          $package_lengths[$id] = max($package_lengths[$id], $length);
        }
      }
    }

    return $package_lengths;
  }

  public static function splitPath($path) {
    $result = array(
      '/',
    );

    $parts = explode('/', $path);
    $buffer = '/';
    foreach ($parts as $part) {
      if (!strlen($part)) {
        continue;
      }

      $buffer = $buffer.$part.'/';
      $result[] = $buffer;
    }

    return $result;
  }

  public function attachPaths(array $paths) {
    assert_instances_of($paths, 'PhabricatorOwnersPath');
    $this->paths = $paths;

    // Drop this cache if we're attaching new paths.
    $this->pathRepositoryMap = array();

    return $this;
  }

  public function getPaths() {
    return $this->assertAttached($this->paths);
  }

  public function getPathsForRepository($repository_phid) {
    if (isset($this->pathRepositoryMap[$repository_phid])) {
      return $this->pathRepositoryMap[$repository_phid];
    }

    $map = array();
    foreach ($this->getPaths() as $path) {
      if ($path->getRepositoryPHID() == $repository_phid) {
        $map[] = $path;
      }
    }

    $this->pathRepositoryMap[$repository_phid] = $map;

    return $this->pathRepositoryMap[$repository_phid];
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

  public function getMonogram() {
    return 'O'.$this->getID();
  }

  public function getURI() {
    // TODO: Move these to "/O123" for consistency.
    return '/owners/package/'.$this->getID().'/';
  }

  public function newAuditingRule() {
    return PhabricatorOwnersAuditRule::newFromState($this->getAuditingState());
  }

  public function getHasStrongAuthority() {
    return ($this->getAuthorityMode() === self::AUTHORITY_STRONG);
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

  public function getApplicationTransactionTemplate() {
    return new PhabricatorOwnersPackageTransaction();
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
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('review')
        ->setType('map<string, wild>')
        ->setDescription(pht('Auto review information.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('audit')
        ->setType('map<string, wild>')
        ->setDescription(pht('Auto audit information.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('dominion')
        ->setType('map<string, wild>')
        ->setDescription(pht('Dominion setting information.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('authority')
        ->setType('map<string, wild>')
        ->setDescription(pht('Authority setting information.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('ignored')
        ->setType('map<string, wild>')
        ->setDescription(pht('Ignored attribute information.')),
    );
  }

  public function getFieldValuesForConduit() {
    $owner_list = array();
    foreach ($this->getOwners() as $owner) {
      $owner_list[] = array(
        'ownerPHID' => $owner->getUserPHID(),
      );
    }

    $review_map = self::getAutoreviewOptionsMap();
    $review_value = $this->getAutoReview();
    if (isset($review_map[$review_value])) {
      $review_label = $review_map[$review_value]['name'];
    } else {
      $review_label = pht('Unknown ("%s")', $review_value);
    }

    $review = array(
      'value' => $review_value,
      'label' => $review_label,
    );

    $audit_rule = $this->newAuditingRule();

    $audit = array(
      'value' => $audit_rule->getKey(),
      'label' => $audit_rule->getDisplayName(),
    );

    $dominion_value = $this->getDominion();
    $dominion_map = self::getDominionOptionsMap();
    if (isset($dominion_map[$dominion_value])) {
      $dominion_label = $dominion_map[$dominion_value]['name'];
      $dominion_short = $dominion_map[$dominion_value]['short'];
    } else {
      $dominion_label = pht('Unknown ("%s")', $dominion_value);
      $dominion_short = pht('Unknown ("%s")', $dominion_value);
    }

    $dominion = array(
      'value' => $dominion_value,
      'label' => $dominion_label,
      'short' => $dominion_short,
    );


    $authority_value = $this->getAuthorityMode();
    $authority_map = self::getAuthorityOptionsMap();
    if (isset($authority_map[$authority_value])) {
      $authority_label = $authority_map[$authority_value]['name'];
      $authority_short = $authority_map[$authority_value]['short'];
    } else {
      $authority_label = pht('Unknown ("%s")', $authority_value);
      $authority_short = pht('Unknown ("%s")', $authority_value);
    }

    $authority = array(
      'value' => $authority_value,
      'label' => $authority_label,
      'short' => $authority_short,
    );

    // Force this to always emit as a JSON object even if empty, never as
    // a JSON list.
    $ignored = $this->getIgnoredPathAttributes();
    if (!$ignored) {
      $ignored = (object)array();
    }

    return array(
      'name' => $this->getName(),
      'description' => $this->getDescription(),
      'status' => $this->getStatus(),
      'owners' => $owner_list,
      'review' => $review,
      'audit' => $audit,
      'dominion' => $dominion,
      'authority' => $authority,
      'ignored' => $ignored,
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


/* -(  PhabricatorFerretInterface  )----------------------------------------- */


  public function newFerretEngine() {
    return new PhabricatorOwnersPackageFerretEngine();
  }


/* -(  PhabricatorNgramsInterface  )----------------------------------------- */


  public function newNgrams() {
    return array(
      id(new PhabricatorOwnersPackageNameNgrams())
        ->setValue($this->getName()),
    );
  }

}
