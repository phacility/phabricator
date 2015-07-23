<?php

final class PhabricatorOwnersPackageQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $ownerPHIDs;
  private $repositoryPHIDs;
  private $namePrefix;

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

  public function withNamePrefix($prefix) {
    $this->namePrefix = $prefix;
    return $this;
  }

  public function newResultObject() {
    return new PhabricatorOwnersPackage();
  }

  protected function loadPage() {
    return $this->loadStandardPage(new PhabricatorOwnersPackage());
  }

  protected function buildJoinClauseParts(AphrontDatabaseConnection $conn) {
    $joins = parent::buildJoinClauseParts($conn);

    if ($this->ownerPHIDs !== null) {
      $joins[] = qsprintf(
        $conn,
        'JOIN %T o ON o.packageID = p.id',
        id(new PhabricatorOwnersOwner())->getTableName());
    }

    if ($this->repositoryPHIDs !== null) {
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

    return $where;
  }

  protected function shouldGroupQueryResultRows() {
    if ($this->repositoryPHIDs) {
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

}
