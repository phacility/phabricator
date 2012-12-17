<?php

final class DrydockLeaseQuery extends PhabricatorOffsetPagedQuery {

  private $ids;
  private $resourceIDs;
  private $needResources;

  public function withResourceIDs(array $ids) {
    $this->resourceIDs = $ids;
    return $this;
  }

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function needResources($need_resources) {
    $this->needResources = $need_resources;
    return $this;
  }

  public function execute() {
    $table = new DrydockLease();
    $conn_r = $table->establishConnection('r');

    $data = queryfx_all(
      $conn_r,
      'SELECT lease.* FROM %T lease %Q %Q %Q',
      $table->getTableName(),
      $this->buildWhereClause($conn_r),
      $this->buildOrderClause($conn_r),
      $this->buildLimitClause($conn_r));

    $leases = $table->loadAllFromArray($data);

    if ($leases && $this->needResources) {
      $resources = id(new DrydockResource())->loadAllWhere(
        'id IN (%Ld)',
        mpull($leases, 'getResourceID'));

      foreach ($leases as $lease) {
        if ($lease->getResourceID()) {
          $resource = idx($resources, $lease->getResourceID());
          if ($resource) {
            $lease->attachResource($resource);
          }
        }
      }
    }

    return $leases;
  }

  private function buildWhereClause(AphrontDatabaseConnection $conn_r) {
    $where = array();

    if ($this->resourceIDs) {
      $where[] = qsprintf(
        $conn_r,
        'resourceID IN (%Ld)',
        $this->resourceIDs);
    }

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
