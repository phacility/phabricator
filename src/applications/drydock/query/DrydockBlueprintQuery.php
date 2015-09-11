<?php

final class DrydockBlueprintQuery extends DrydockQuery {

  private $ids;
  private $phids;
  private $datasourceQuery;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withDatasourceQuery($query) {
    $this->datasourceQuery = $query;
    return $this;
  }

  protected function loadPage() {
    $table = new DrydockBlueprint();
    $conn_r = $table->establishConnection('r');

    $data = queryfx_all(
      $conn_r,
      'SELECT blueprint.* FROM %T blueprint %Q %Q %Q',
      $table->getTableName(),
      $this->buildWhereClause($conn_r),
      $this->buildOrderClause($conn_r),
      $this->buildLimitClause($conn_r));

    $blueprints = $table->loadAllFromArray($data);

    $implementations =
      DrydockBlueprintImplementation::getAllBlueprintImplementations();

    foreach ($blueprints as $blueprint) {
      if (array_key_exists($blueprint->getClassName(), $implementations)) {
        $blueprint->attachImplementation(
          $implementations[$blueprint->getClassName()]);
      }
    }

    return $blueprints;
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

    if ($this->datasourceQuery !== null) {
      $where[] = qsprintf(
        $conn_r,
        'blueprintName LIKE %>',
        $this->datasourceQuery);
    }

    return $this->formatWhereClause($where);
  }

}
