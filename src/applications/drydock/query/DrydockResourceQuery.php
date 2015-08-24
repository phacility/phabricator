<?php

final class DrydockResourceQuery extends DrydockQuery {

  private $ids;
  private $phids;
  private $statuses;
  private $types;
  private $blueprintPHIDs;
  private $datasourceQuery;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withTypes(array $types) {
    $this->types = $types;
    return $this;
  }

  public function withStatuses(array $statuses) {
    $this->statuses = $statuses;
    return $this;
  }

  public function withBlueprintPHIDs(array $blueprint_phids) {
    $this->blueprintPHIDs = $blueprint_phids;
    return $this;
  }

  public function withDatasourceQuery($query) {
    $this->datasourceQuery = $query;
    return $this;
  }

  protected function loadPage() {
    $table = new DrydockResource();
    $conn_r = $table->establishConnection('r');

    $data = queryfx_all(
      $conn_r,
      'SELECT resource.* FROM %T resource %Q %Q %Q',
      $table->getTableName(),
      $this->buildWhereClause($conn_r),
      $this->buildOrderClause($conn_r),
      $this->buildLimitClause($conn_r));

    $resources = $table->loadAllFromArray($data);

    return $resources;
  }

  protected function buildWhereClause(AphrontDatabaseConnection $conn_r) {
    $where = array();

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn_r,
        'id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn_r,
        'phid IN (%Ls)',
        $this->phids);
    }

    if ($this->types !== null) {
      $where[] = qsprintf(
        $conn_r,
        'type IN (%Ls)',
        $this->types);
    }

    if ($this->statuses !== null) {
      $where[] = qsprintf(
        $conn_r,
        'status IN (%Ls)',
        $this->statuses);
    }

    if ($this->blueprintPHIDs !== null) {
      $where[] = qsprintf(
        $conn_r,
        'blueprintPHID IN (%Ls)',
        $this->blueprintPHIDs);
    }

    if ($this->datasourceQuery !== null) {
      $where[] = qsprintf(
        $conn_r,
        'name LIKE %>',
        $this->datasourceQuery);
    }

    $where[] = $this->buildPagingClause($conn_r);

    return $this->formatWhereClause($where);
  }

}
