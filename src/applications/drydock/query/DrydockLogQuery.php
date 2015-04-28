<?php

final class DrydockLogQuery extends DrydockQuery {

  private $resourceIDs;
  private $leaseIDs;

  public function withResourceIDs(array $ids) {
    $this->resourceIDs = $ids;
    return $this;
  }

  public function withLeaseIDs(array $ids) {
    $this->leaseIDs = $ids;
    return $this;
  }

  protected function loadPage() {
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

  protected function willFilterPage(array $logs) {
    $resource_ids = array_filter(mpull($logs, 'getResourceID'));
    if ($resource_ids) {
      $resources = id(new DrydockResourceQuery())
        ->setParentQuery($this)
        ->setViewer($this->getViewer())
        ->withIDs($resource_ids)
        ->execute();
    } else {
      $resources = array();
    }

    foreach ($logs as $key => $log) {
      $resource = null;
      if ($log->getResourceID()) {
        $resource = idx($resources, $log->getResourceID());
        if (!$resource) {
          unset($logs[$key]);
          continue;
        }
      }
      $log->attachResource($resource);
    }

    $lease_ids = array_filter(mpull($logs, 'getLeaseID'));
    if ($lease_ids) {
      $leases = id(new DrydockLeaseQuery())
        ->setParentQuery($this)
        ->setViewer($this->getViewer())
        ->withIDs($lease_ids)
        ->execute();
    } else {
      $leases = array();
    }

    foreach ($logs as $key => $log) {
      $lease = null;
      if ($log->getLeaseID()) {
        $lease = idx($leases, $log->getLeaseID());
        if (!$lease) {
          unset($logs[$key]);
          continue;
        }
      }
      $log->attachLease($lease);
    }

    // These logs are meaningless and their policies aren't computable. They
    // shouldn't exist, but throw them away if they do.
    foreach ($logs as $key => $log) {
      if (!$log->getResource() && !$log->getLease()) {
        unset($logs[$key]);
      }
    }

    return $logs;
  }

  protected function buildWhereClause(AphrontDatabaseConnection $conn_r) {
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

    $where[] = $this->buildPagingClause($conn_r);

    return $this->formatWhereClause($where);
  }

}
