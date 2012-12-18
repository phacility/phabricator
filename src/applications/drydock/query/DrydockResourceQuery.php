<?php

final class DrydockResourceQuery extends PhabricatorOffsetPagedQuery {

  private $ids;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function execute() {
    $table = new DrydockResource();
    $conn_r = $table->establishConnection('r');

    $data = queryfx_all(
      $conn_r,
      'SELECT resource.* FROM %T resource %Q %Q %Q',
      $table->getTableName(),
      $this->buildWhereClause($conn_r),
      $this->buildOrderClause($conn_r),
      $this->buildLimitClause($conn_r));

    $resources = $table->loadAllFromArray($data);

    return $resources;
  }

  private function buildWhereClause(AphrontDatabaseConnection $conn_r) {
    $where = array();

    if ($this->ids) {
      $where[] = qsprintf(
        $conn_r,
        'id IN (%Ld)',
        $this->ids);
    }

    return $this->formatWhereClause($where);
  }

  private function buildOrderClause(AphrontDatabaseConnection $conn_r) {
    return qsprintf($conn_r, 'ORDER BY id DESC');
  }

}
