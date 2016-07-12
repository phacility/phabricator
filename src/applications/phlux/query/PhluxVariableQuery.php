<?php

final class PhluxVariableQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $keys;
  private $phids;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

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

  protected function buildWhereClause(AphrontDatabaseConnection $conn_r) {
    $where = array();

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn_r,
        'id IN (%Ld)',
        $this->ids);
    }

    if ($this->keys !== null) {
      $where[] = qsprintf(
        $conn_r,
        'variableKey IN (%Ls)',
        $this->keys);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn_r,
        'phid IN (%Ls)',
        $this->phids);
    }

    $where[] = $this->buildPagingClause($conn_r);

    return $this->formatWhereClause($where);
  }

  protected function getDefaultOrderVector() {
    return array('key');
  }

  public function getOrderableColumns() {
    return array(
      'key' => array(
        'column' => 'variableKey',
        'type' => 'string',
        'reverse' => true,
        'unique' => true,
      ),
    );
  }

  protected function getPagingValueMap($cursor, array $keys) {
    $object = $this->loadCursorObject($cursor);
    return array(
      'key' => $object->getVariableKey(),
    );
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorPhluxApplication';
  }

}
