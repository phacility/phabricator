<?php

final class DrydockLogQuery extends PhabricatorCursorPagedPolicyAwareQuery {

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

  public function loadPage() {
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

  public function willFilterPage(array $logs) {
    $resource_ids = mpull($logs, 'getResourceID');
    $resources = id(new DrydockResourceQuery())
      ->setParentQuery($this)
      ->setViewer($this->getViewer())
      ->withIDs($resource_ids)
      ->execute();

    foreach ($logs as $key => $log) {
      $resource = idx($resources, $log->getResourceID());
      $log->attachResource($resource);
    }

    return $logs;
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

    $where[] = $this->buildPagingClause($conn_r);

    return $this->formatWhereClause($where);
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorApplicationDrydock';
  }

}
