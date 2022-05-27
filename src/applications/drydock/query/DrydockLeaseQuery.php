<?php

final class DrydockLeaseQuery extends DrydockQuery {

  private $ids;
  private $phids;
  private $resourcePHIDs;
  private $ownerPHIDs;
  private $statuses;
  private $datasourceQuery;
  private $needUnconsumedCommands;
  private $minModified;
  private $maxModified;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withResourcePHIDs(array $phids) {
    $this->resourcePHIDs = $phids;
    return $this;
  }

  public function withOwnerPHIDs(array $phids) {
    $this->ownerPHIDs = $phids;
    return $this;
  }

  public function withStatuses(array $statuses) {
    $this->statuses = $statuses;
    return $this;
  }

  public function withDatasourceQuery($query) {
    $this->datasourceQuery = $query;
    return $this;
  }

  public function withDateModifiedBetween($min_epoch, $max_epoch) {
    $this->minModified = $min_epoch;
    $this->maxModified = $max_epoch;
    return $this;
  }

  public function needUnconsumedCommands($need) {
    $this->needUnconsumedCommands = $need;
    return $this;
  }

  public function newResultObject() {
    return new DrydockLease();
  }

  protected function willFilterPage(array $leases) {
    $resource_phids = array_filter(mpull($leases, 'getResourcePHID'));
    if ($resource_phids) {
      $resources = id(new DrydockResourceQuery())
        ->setParentQuery($this)
        ->setViewer($this->getViewer())
        ->withPHIDs(array_unique($resource_phids))
        ->execute();
      $resources = mpull($resources, null, 'getPHID');
    } else {
      $resources = array();
    }

    foreach ($leases as $key => $lease) {
      $resource = null;
      if ($lease->getResourcePHID()) {
        $resource = idx($resources, $lease->getResourcePHID());
        if (!$resource) {
          $this->didRejectResult($lease);
          unset($leases[$key]);
          continue;
        }
      }
      $lease->attachResource($resource);
    }

    return $leases;
  }

  protected function didFilterPage(array $leases) {
    if ($this->needUnconsumedCommands) {
      $commands = id(new DrydockCommandQuery())
        ->setViewer($this->getViewer())
        ->setParentQuery($this)
        ->withTargetPHIDs(mpull($leases, 'getPHID'))
        ->withConsumed(false)
        ->execute();
      $commands = mgroup($commands, 'getTargetPHID');

      foreach ($leases as $lease) {
        $list = idx($commands, $lease->getPHID(), array());
        $lease->attachUnconsumedCommands($list);
      }
    }

    return $leases;
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->resourcePHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'resourcePHID IN (%Ls)',
        $this->resourcePHIDs);
    }

    if ($this->ownerPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'ownerPHID IN (%Ls)',
        $this->ownerPHIDs);
    }

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn,
        'phid IN (%Ls)',
        $this->phids);
    }

    if ($this->statuses !== null) {
      $where[] = qsprintf(
        $conn,
        'status IN (%Ls)',
        $this->statuses);
    }

    if ($this->datasourceQuery !== null) {
      $where[] = qsprintf(
        $conn,
        'id = %d',
        (int)$this->datasourceQuery);
    }

    if ($this->minModified !== null) {
      $where[] = qsprintf(
        $conn,
        'dateModified >= %d',
        $this->minModified);
    }

    if ($this->maxModified !== null) {
      $where[] = qsprintf(
        $conn,
        'dateModified <= %d',
        $this->maxModified);
    }

    return $where;
  }

}
