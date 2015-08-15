<?php

final class PhabricatorOwnersPackageQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $ownerPHIDs;
  private $repositoryPHIDs;
  private $namePrefix;
  private $needPaths;

  private $controlMap = array();
  private $controlResults;

  /**
   * Owners are direct owners, and members of owning projects.
   */
  public function withOwnerPHIDs(array $phids) {
    $this->ownerPHIDs = $phids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withRepositoryPHIDs(array $phids) {
    $this->repositoryPHIDs = $phids;
    return $this;
  }

  public function withControl($repository_phid, array $paths) {
    if (empty($this->controlMap[$repository_phid])) {
      $this->controlMap[$repository_phid] = array();
    }

    foreach ($paths as $path) {
      $this->controlMap[$repository_phid][$path] = $path;
    }

    // We need to load paths to execute control queries.
    $this->needPaths = true;

    return $this;
  }

  public function withNamePrefix($prefix) {
    $this->namePrefix = $prefix;
    return $this;
  }

  public function needPaths($need_paths) {
    $this->needPaths = $need_paths;
    return $this;
  }

  public function newResultObject() {
    return new PhabricatorOwnersPackage();
  }

  protected function willExecute() {
    $this->controlResults = array();
  }

  protected function loadPage() {
    return $this->loadStandardPage(new PhabricatorOwnersPackage());
  }

  protected function didFilterPage(array $packages) {
    if ($this->needPaths) {
      $package_ids = mpull($packages, 'getID');

      $paths = id(new PhabricatorOwnersPath())->loadAllWhere(
        'packageID IN (%Ld)',
        $package_ids);
      $paths = mgroup($paths, 'getPackageID');

      foreach ($packages as $package) {
        $package->attachPaths(idx($paths, $package->getID(), array()));
      }
    }

    if ($this->controlMap) {
      $this->controlResults += mpull($packages, null, 'getID');
    }

    return $packages;
  }

  protected function buildJoinClauseParts(AphrontDatabaseConnection $conn) {
    $joins = parent::buildJoinClauseParts($conn);

    if ($this->ownerPHIDs !== null) {
      $joins[] = qsprintf(
        $conn,
        'JOIN %T o ON o.packageID = p.id',
        id(new PhabricatorOwnersOwner())->getTableName());
    }

    if ($this->shouldJoinOwnersPathTable()) {
      $joins[] = qsprintf(
        $conn,
        'JOIN %T rpath ON rpath.packageID = p.id',
        id(new PhabricatorOwnersPath())->getTableName());
    }

    return $joins;
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn,
        'p.phid IN (%Ls)',
        $this->phids);
    }

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'p.id IN (%Ld)',
        $this->ids);
    }

    if ($this->repositoryPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'rpath.repositoryPHID IN (%Ls)',
        $this->repositoryPHIDs);
    }

    if ($this->ownerPHIDs !== null) {
      $base_phids = $this->ownerPHIDs;

      $projects = id(new PhabricatorProjectQuery())
        ->setViewer($this->getViewer())
        ->withMemberPHIDs($base_phids)
        ->execute();
      $project_phids = mpull($projects, 'getPHID');

      $all_phids = array_merge($base_phids, $project_phids);

      $where[] = qsprintf(
        $conn,
        'o.userPHID IN (%Ls)',
        $all_phids);
    }

    if (strlen($this->namePrefix)) {
      // NOTE: This is a hacky mess, but this column is currently case
      // sensitive and unique.
      $where[] = qsprintf(
        $conn,
        'LOWER(p.name) LIKE %>',
        phutil_utf8_strtolower($this->namePrefix));
    }

    if ($this->controlMap) {
      $clauses = array();
      foreach ($this->controlMap as $repository_phid => $paths) {
        $fragments = array();
        foreach ($paths as $path) {
          foreach (PhabricatorOwnersPackage::splitPath($path) as $fragment) {
            $fragments[$fragment] = $fragment;
          }
        }

        $clauses[] = qsprintf(
          $conn,
          '(rpath.repositoryPHID = %s AND rpath.path IN (%Ls))',
          $repository_phid,
          $fragments);
      }
      $where[] = implode(' OR ', $clauses);
    }

    return $where;
  }

  protected function shouldGroupQueryResultRows() {
    if ($this->shouldJoinOwnersPathTable()) {
      return true;
    }

    if ($this->ownerPHIDs) {
      return true;
    }

    return parent::shouldGroupQueryResultRows();
  }

  public function getBuiltinOrders() {
    return array(
      'name' => array(
        'vector' => array('name'),
        'name' => pht('Name'),
      ),
    ) + parent::getBuiltinOrders();
  }

  public function getOrderableColumns() {
    return parent::getOrderableColumns() + array(
      'name' => array(
        'table' => $this->getPrimaryTableAlias(),
        'column' => 'name',
        'type' => 'string',
        'unique' => true,
        'reverse' => true,
      ),
    );
  }

  protected function getPagingValueMap($cursor, array $keys) {
    $package = $this->loadCursorObject($cursor);
    return array(
      'id' => $package->getID(),
      'name' => $package->getName(),
    );
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorOwnersApplication';
  }

  protected function getPrimaryTableAlias() {
    return 'p';
  }

  private function shouldJoinOwnersPathTable() {
    if ($this->repositoryPHIDs !== null) {
      return true;
    }

    if ($this->controlMap) {
      return true;
    }

    return false;
  }


/* -(  Path Control  )------------------------------------------------------- */


  /**
   * Get the package which controls a path, if one exists.
   *
   * @return PhabricatorOwnersPackage|null Package, if one exists.
   */
  public function getControllingPackageForPath($repository_phid, $path) {
    $packages = $this->getControllingPackagesForPath($repository_phid, $path);

    if (!$packages) {
      return null;
    }

    return head($packages);
  }


  /**
   * Get a list of all packages which control a path or its parent directories,
   * ordered from weakest to strongest.
   *
   * The first package has the most specific claim on the path; the last
   * package has the most general claim.
   *
   * @return list<PhabricatorOwnersPackage> List of controlling packages.
   */
  public function getControllingPackagesForPath($repository_phid, $path) {
    if (!isset($this->controlMap[$repository_phid][$path])) {
      throw new PhutilInvalidStateException('withControl');
    }

    if ($this->controlResults === null) {
      throw new PhutilInvalidStateException('execute');
    }

    $packages = $this->controlResults;

    $matches = array();
    foreach ($packages as $package_id => $package) {
      $best_match = null;
      $include = false;

      foreach ($package->getPaths() as $package_path) {
        $strength = $package_path->getPathMatchStrength($path);
        if ($strength > $best_match) {
          $best_match = $strength;
          $include = !$package_path->getExcluded();
        }
      }

      if ($best_match && $include) {
        $matches[$package_id] = array(
          'strength' => $best_match,
          'package' => $package,
        );
      }
    }

    $matches = isort($matches, 'strength');
    $matches = array_reverse($matches);

    return array_values(ipull($matches, 'package'));
  }

}
