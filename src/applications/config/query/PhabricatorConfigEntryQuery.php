<?php

final class PhabricatorConfigEntryQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $phids;
  private $ids;

  public function withIDs($ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs($phids) {
    $this->phids = $phids;
    return $this;
  }

  protected function loadPage() {
    $table = new PhabricatorConfigEntry();
    $conn_r = $table->establishConnection('r');

    $data = queryfx_all(
      $conn_r,
      'SELECT * FROM %T %Q %Q %Q',
      $table->getTableName(),
      $this->buildWhereClause($conn_r),
      $this->buildOrderClause($conn_r),
      $this->buildLimitClause($conn_r));

    return $table->loadAllFromArray($data);
  }

  protected function buildWhereClause(AphrontDatabaseConnection $conn) {
    $where = array();

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn,
        'phid IN (%Ls)',
        $this->phids);
    }

    $where[] = $this->buildPagingClause($conn);

    return $this->formatWhereClause($conn, $where);
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorConfigApplication';
  }

}
