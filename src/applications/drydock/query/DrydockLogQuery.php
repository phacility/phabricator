<?php

final class DrydockLogQuery extends PhabricatorOffsetPagedQuery {

  const ORDER_EPOCH   = 'order-epoch';
  const ORDER_ID      = 'order-id';

  private $resourceIDs;
  private $leaseIDs;
  private $afterID;
  private $order = self::ORDER_EPOCH;

  public function withResourceIDs(array $ids) {
    $this->resourceIDs = $ids;
    return $this;
  }

  public function withLeaseIDs(array $ids) {
    $this->leaseIDs = $ids;
    return $this;
  }

  public function setOrder($order) {
    $this->order = $order;
    return $this;
  }

  public function withAfterID($id) {
    $this->afterID = $id;
    return $this;
  }

  public function execute() {
    $table = new DrydockLog();
    $conn_r = $table->establishConnection('r');

    $data = queryfx_all(
      $conn_r,
      'SELECT log.* FROM %T log %Q %Q %Q',
      $table->getTableName(),
      $this->buildWhereClause($conn_r),
      $this->buildOrderClause($conn_r),
      $this->buildLimitClause($conn_r));

    return $table->loadAllFromArray($data);
  }

  private function buildWhereClause(AphrontDatabaseConnection $conn_r) {
    $where = array();

    if ($this->resourceIDs) {
      $where[] = qsprintf(
        $conn_r,
        'resourceID IN (%Ld)',
        $this->resourceIDs);
    }

    if ($this->leaseIDs) {
      $where[] = qsprintf(
        $conn_r,
        'leaseID IN (%Ld)',
        $this->leaseIDs);
    }

    if ($this->afterID) {
      $where[] = qsprintf(
        $conn_r,
        'id > %d',
        $this->afterID);
    }

    return $this->formatWhereClause($where);
  }

  private function buildOrderClause(AphrontDatabaseConnection $conn_r) {
    switch ($this->order) {
      case self::ORDER_EPOCH:
        return 'ORDER BY log.epoch DESC';
      case self::ORDER_ID:
        return 'ORDER BY id ASC';
      default:
        throw new Exception("Unknown order '{$this->order}'!");
    }
  }

}
