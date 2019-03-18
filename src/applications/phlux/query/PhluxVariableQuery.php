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

  protected function buildWhereClause(AphrontDatabaseConnection $conn) {
    $where = array();

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'id IN (%Ld)',
        $this->ids);
    }

    if ($this->keys !== null) {
      $where[] = qsprintf(
        $conn,
        'variableKey IN (%Ls)',
        $this->keys);
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

  protected function newPagingMapFromPartialObject($object) {
    return array(
      'id' => (int)$object->getID(),
      'key' => $object->getVariableKey(),
    );
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorPhluxApplication';
  }

}
