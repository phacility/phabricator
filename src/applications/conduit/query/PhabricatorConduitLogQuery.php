<?php

final class PhabricatorConduitLogQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $methods;

  public function withMethods(array $methods) {
    $this->methods = $methods;
    return $this;
  }

  protected function loadPage() {
    $table = new PhabricatorConduitMethodCallLog();
    $conn_r = $table->establishConnection('r');

    $data = queryfx_all(
      $conn_r,
      'SELECT * FROM %T %Q %Q %Q',
      $table->getTableName(),
      $this->buildWhereClause($conn_r),
      $this->buildOrderClause($conn_r),
      $this->buildLimitClause($conn_r));

    return $table->loadAllFromArray($data);;
  }

  protected function buildWhereClause(AphrontDatabaseConnection $conn_r) {
    $where = array();

    if ($this->methods) {
      $where[] = qsprintf(
        $conn_r,
        'method IN (%Ls)',
        $this->methods);
    }

    $where[] = $this->buildPagingClause($conn_r);
    return $this->formatWhereClause($where);
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorConduitApplication';
  }

}
