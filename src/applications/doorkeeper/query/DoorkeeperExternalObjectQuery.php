<?php

final class DoorkeeperExternalObjectQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  protected $phids;
  protected $objectKeys;

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withObjectKeys(array $keys) {
    $this->objectKeys = $keys;
    return $this;
  }

  protected function loadPage() {
    $table = new DoorkeeperExternalObject();
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

  protected function buildWhereClause(AphrontDatabaseConnection $conn_r) {
    $where = array();

    if ($this->phids) {
      $where[] = qsprintf(
        $conn_r,
        'phid IN (%Ls)',
        $this->phids);
    }

    if ($this->objectKeys) {
      $where[] = qsprintf(
        $conn_r,
        'objectKey IN (%Ls)',
        $this->objectKeys);
    }

    $where[] = $this->buildPagingClause($conn_r);
    return $this->formatWhereClause($where);
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorDoorkeeperApplication';
  }

}
