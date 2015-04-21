<?php

final class PhabricatorOwnersPackageQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $phids;
  private $ownerPHIDs;

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

  protected function loadPage() {
    $table = new PhabricatorOwnersPackage();
    $conn_r = $table->establishConnection('r');

    $data = queryfx_all(
      $conn_r,
      'SELECT p.* FROM %T p %Q %Q %Q %Q',
      $table->getTableName(),
      $this->buildJoinClause($conn_r),
      $this->buildWhereClause($conn_r),
      $this->buildOrderClause($conn_r),
      $this->buildLimitClause($conn_r));

    return $table->loadAllFromArray($data);
  }

  protected function buildJoinClause(AphrontDatabaseConnection $conn_r) {
    $joins = array();

    if ($this->ownerPHIDs) {
      $joins[] = qsprintf(
        $conn_r,
        'JOIN %T o ON o.packageID = p.id',
        id(new PhabricatorOwnersOwner())->getTableName());
    }

    return implode(' ', $joins);
  }

  protected function buildWhereClause(AphrontDatabaseConnection $conn_r) {
    $where = array();

    if ($this->phids) {
      $where[] = qsprintf(
        $conn_r,
        'p.phid IN (%Ls)',
        $this->phids);
    }

    if ($this->ownerPHIDs) {
      $base_phids = $this->ownerPHIDs;

      $query = new PhabricatorProjectQuery();
      $query->setViewer($this->getViewer());
      $query->withMemberPHIDs($base_phids);
      $projects = $query->execute();
      $project_phids = mpull($projects, 'getPHID');

      $all_phids = array_merge($base_phids, $project_phids);

      $where[] = qsprintf(
        $conn_r,
        'o.userPHID IN (%Ls)',
        $all_phids);
    }

    $where[] = $this->buildPagingClause($conn_r);
    return $this->formatWhereClause($where);
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorOwnersApplication';
  }

}
