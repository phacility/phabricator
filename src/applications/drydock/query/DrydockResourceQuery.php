<?php

final class DrydockResourceQuery extends DrydockQuery {

  private $ids;
  private $phids;
  private $statuses;
  private $types;
  private $blueprintPHIDs;
  private $datasourceQuery;
  private $needUnconsumedCommands;

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

  public function needUnconsumedCommands($need) {
    $this->needUnconsumedCommands = $need;
    return $this;
  }

  public function newResultObject() {
    return new DrydockResource();
  }

  protected function loadPage() {
    return $this->loadStandardPage($this->newResultObject());
  }

  protected function willFilterPage(array $resources) {
    $blueprint_phids = mpull($resources, 'getBlueprintPHID');

    $blueprints = id(new DrydockBlueprintQuery())
      ->setViewer($this->getViewer())
      ->withPHIDs($blueprint_phids)
      ->execute();
    $blueprints = mpull($blueprints, null, 'getPHID');

    foreach ($resources as $key => $resource) {
      $blueprint = idx($blueprints, $resource->getBlueprintPHID());
      if (!$blueprint) {
        $this->didRejectResult($resource);
        unset($resources[$key]);
        continue;
      }
      $resource->attachBlueprint($blueprint);
    }

    return $resources;
  }

  protected function didFilterPage(array $resources) {
    if ($this->needUnconsumedCommands) {
      $commands = id(new DrydockCommandQuery())
        ->setViewer($this->getViewer())
        ->setParentQuery($this)
        ->withTargetPHIDs(mpull($resources, 'getPHID'))
        ->withConsumed(false)
        ->execute();
      $commands = mgroup($commands, 'getTargetPHID');

      foreach ($resources as $resource) {
        $list = idx($commands, $resource->getPHID(), array());
        $resource->attachUnconsumedCommands($list);
      }
    }

    return $resources;
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

    if ($this->types !== null) {
      $where[] = qsprintf(
        $conn,
        'type IN (%Ls)',
        $this->types);
    }

    if ($this->statuses !== null) {
      $where[] = qsprintf(
        $conn,
        'status IN (%Ls)',
        $this->statuses);
    }

    if ($this->blueprintPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'blueprintPHID IN (%Ls)',
        $this->blueprintPHIDs);
    }

    if ($this->datasourceQuery !== null) {
      $where[] = qsprintf(
        $conn,
        'name LIKE %>',
        $this->datasourceQuery);
    }

    return $where;
  }

}
