<?php

final class DrydockLeaseQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $resourceIDs;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withResourceIDs(array $ids) {
    $this->resourceIDs = $ids;
    return $this;
  }

  public function loadPage() {
    $table = new DrydockLease();
    $conn_r = $table->establishConnection('r');

    $data = queryfx_all(
      $conn_r,
      'SELECT lease.* FROM %T lease %Q %Q %Q',
      $table->getTableName(),
      $this->buildWhereClause($conn_r),
      $this->buildOrderClause($conn_r),
      $this->buildLimitClause($conn_r));

    return $table->loadAllFromArray($data);
  }

  public function willFilterPage(array $leases) {
    $resources = id(new DrydockResourceQuery())
      ->setParentQuery($this)
      ->setViewer($this->getViewer())
      ->withIDs(mpull($leases, 'getResourceID'))
      ->execute();

    foreach ($leases as $key => $lease) {
      $resource = idx($resources, $lease->getResourceID());
      if (!$resource) {
        unset($leases[$key]);
        continue;
      }
      $lease->attachResource($resource);
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

    $where[] = $this->buildPagingClause($conn_r);

    return $this->formatWhereClause($where);
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorApplicationDrydock';
  }

}
