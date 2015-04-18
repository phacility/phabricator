<?php

final class DrydockLeaseQuery extends DrydockQuery {

  private $ids;
  private $phids;
  private $resourceIDs;
  private $statuses;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withResourceIDs(array $ids) {
    $this->resourceIDs = $ids;
    return $this;
  }

  public function withStatuses(array $statuses) {
    $this->statuses = $statuses;
    return $this;
  }

  protected function loadPage() {
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

  protected function willFilterPage(array $leases) {
    $resource_ids = array_filter(mpull($leases, 'getResourceID'));
    if ($resource_ids) {
      $resources = id(new DrydockResourceQuery())
        ->setParentQuery($this)
        ->setViewer($this->getViewer())
        ->withIDs($resource_ids)
        ->execute();
    } else {
      $resources = array();
    }

    foreach ($leases as $key => $lease) {
      $resource = null;
      if ($lease->getResourceID()) {
        $resource = idx($resources, $lease->getResourceID());
        if (!$resource) {
          unset($leases[$key]);
          continue;
        }
      }
      $lease->attachResource($resource);
    }

    return $leases;
  }

  protected function buildWhereClause(AphrontDatabaseConnection $conn_r) {
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

    if ($this->phids) {
      $where[] = qsprintf(
        $conn_r,
        'phid IN (%Ls)',
        $this->phids);
    }

    if ($this->statuses) {
      $where[] = qsprintf(
        $conn_r,
        'status IN (%Ld)',
        $this->statuses);
    }

    $where[] = $this->buildPagingClause($conn_r);

    return $this->formatWhereClause($where);
  }

}
