<?php

final class PhluxVariableQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $keys;
  private $phids;

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withKeys(array $keys) {
    $this->keys = $keys;
    return $this;
  }

  protected function loadPage() {
    $table = new PhluxVariable();
    $conn_r = $table->establishConnection('r');

    $rows = queryfx_all(
      $conn_r,
      'SELECT * FROM %T %Q %Q %Q',
      $table->getTableName(),
      $this->buildWhereClause($conn_r),
      $this->buildOrderClause($conn_r),
      $this->buildLimitClause($conn_r));

    return $table->loadAllFromArray($rows);
  }

  private function buildWhereClause(AphrontDatabaseConnection $conn_r) {
    $where = array();

    $where[] = $this->buildPagingClause($conn_r);

    if ($this->keys) {
      $where[] = qsprintf(
        $conn_r,
        'variableKey IN (%Ls)',
        $this->keys);
    }

    if ($this->phids) {
      $where[] = qsprintf(
        $conn_r,
        'phid IN (%Ls)',
        $this->phids);
    }

    return $this->formatWhereClause($where);
  }

  protected function getPagingColumn() {
    return 'variableKey';
  }

  protected function getPagingValue($result) {
    return $result->getVariableKey();
  }

  protected function getReversePaging() {
    return true;
  }

}
