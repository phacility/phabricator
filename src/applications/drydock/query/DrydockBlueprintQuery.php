<?php

final class DrydockBlueprintQuery extends DrydockQuery {

  private $ids;
  private $phids;
  private $blueprintClasses;
  private $datasourceQuery;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withBlueprintClasses(array $classes) {
    $this->blueprintClasses = $classes;
    return $this;
  }

  public function withDatasourceQuery($query) {
    $this->datasourceQuery = $query;
    return $this;
  }

  public function newResultObject() {
    return new DrydockBlueprint();
  }

  protected function loadPage() {
    return $this->loadStandardPage($this->newResultObject());
  }

  protected function willFilterPage(array $blueprints) {
    $impls = DrydockBlueprintImplementation::getAllBlueprintImplementations();
    foreach ($blueprints as $key => $blueprint) {
      $impl = idx($impls, $blueprint->getClassName());
      if (!$impl) {
        $this->didRejectResult($blueprint);
        unset($blueprints[$key]);
        continue;
      }
      $impl = clone $impl;
      $blueprint->attachImplementation($impl);
    }

    return $blueprints;
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

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

    if ($this->datasourceQuery !== null) {
      $where[] = qsprintf(
        $conn,
        'blueprintName LIKE %>',
        $this->datasourceQuery);
    }

    if ($this->blueprintClasses !== null) {
      $where[] = qsprintf(
        $conn,
        'className IN (%Ls)',
        $this->blueprintClasses);
    }

    return $where;
  }

  public function getOrderableColumns() {
    // TODO: Blueprints implement CustomFields, but can not be ordered by
    // custom field classes because the custom fields are not global. There
    // is no graceful way to handle this in ApplicationSearch at the moment.
    // Just brute force around it until we can clean this up.

    return array(
      'id' => array(
        'table' => $this->getPrimaryTableAlias(),
        'column' => 'id',
        'reverse' => false,
        'type' => 'int',
        'unique' => true,
      ),
    );
  }

}
