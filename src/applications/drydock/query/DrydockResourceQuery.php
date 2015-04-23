<?php

final class DrydockResourceQuery extends DrydockQuery {

  private $ids;
  private $phids;
  private $statuses;
  private $types;
  private $blueprintPHIDs;

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

    if ($this->types) {
      $where[] = qsprintf(
        $conn_r,
        'type IN (%Ls)',
        $this->types);
    }

    if ($this->statuses) {
      $where[] = qsprintf(
        $conn_r,
        'status IN (%Ls)',
        $this->statuses);
    }

    if ($this->blueprintPHIDs) {
      $where[] = qsprintf(
        $conn_r,
        'blueprintPHID IN (%Ls)',
        $this->blueprintPHIDs);
    }

    $where[] = $this->buildPagingClause($conn_r);

    return $this->formatWhereClause($where);
  }

}
