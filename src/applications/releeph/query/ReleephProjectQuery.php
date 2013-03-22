<?php

final class ReleephProjectQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $active;

  private $order    = 'order-id';
  const ORDER_ID    = 'order-id';
  const ORDER_NAME  = 'order-name';

  public function withActive($active) {
    $this->active = $active;
    return $this;
  }

  public function setOrder($order) {
    $this->order = $order;
    return $this;
  }

  public function loadPage() {
    $table = new ReleephProject();
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

    if ($this->active !== null) {
      $where[] = qsprintf(
        $conn_r,
        'isActive = %d',
        $this->active);
    }

    $where[] = $this->buildPagingClause($conn_r);

    return $this->formatWhereClause($where);
  }

  protected function getReversePaging() {
    switch ($this->order) {
      case self::ORDER_NAME:
        return true;
    }
    return parent::getReversePaging();
  }

  protected function getPagingValue($result) {
    switch ($this->order) {
      case self::ORDER_NAME:
        return $result->getName();
    }
    return parent::getPagingValue();
  }

  protected function getPagingColumn() {
    switch ($this->order) {
      case self::ORDER_NAME:
        return 'name';
      case self::ORDER_ID:
        return parent::getPagingColumn();
      default:
        throw new Exception("Uknown order '{$this->order}'!");
    }
  }

}
