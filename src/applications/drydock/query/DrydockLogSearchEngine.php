<?php

final class DrydockLogSearchEngine extends PhabricatorApplicationSearchEngine {

  private $blueprint;
  private $resource;
  private $lease;

  public function setBlueprint(DrydockBlueprint $blueprint) {
    $this->blueprint = $blueprint;
    return $this;
  }

  public function getBlueprint() {
    return $this->blueprint;
  }

  public function setResource(DrydockResource $resource) {
    $this->resource = $resource;
    return $this;
  }

  public function getResource() {
    return $this->resource;
  }

  public function setLease(DrydockLease $lease) {
    $this->lease = $lease;
    return $this;
  }

  public function getLease() {
    return $this->lease;
  }

  public function canUseInPanelContext() {
    // Prevent use on Dashboard panels since all log queries currently need a
    // parent object and these don't seem particularly useful in any case.
    return false;
  }

  public function getResultTypeDescription() {
    return pht('Drydock Logs');
  }

  public function getApplicationClassName() {
    return 'PhabricatorDrydockApplication';
  }

  public function newQuery() {
    $query = new DrydockLogQuery();

    $blueprint = $this->getBlueprint();
    if ($blueprint) {
      $query->withBlueprintPHIDs(array($blueprint->getPHID()));
    }

    $resource = $this->getResource();
    if ($resource) {
      $query->withResourcePHIDs(array($resource->getPHID()));
    }

    $lease = $this->getLease();
    if ($lease) {
      $query->withLeasePHIDs(array($lease->getPHID()));
    }

    return $query;
  }

  protected function buildQueryFromParameters(array $map) {
    $query = $this->newQuery();

    return $query;
  }

  protected function buildCustomSearchFields() {
    return array();
  }

  protected function getURI($path) {
    $blueprint = $this->getBlueprint();
    if ($blueprint) {
      $id = $blueprint->getID();
      return "/drydock/blueprint/{$id}/logs/{$path}";
    }

    $resource = $this->getResource();
    if ($resource) {
      $id = $resource->getID();
      return "/drydock/resource/{$id}/logs/{$path}";
    }

    $lease = $this->getLease();
    if ($lease) {
      $id = $lease->getID();
      return "/drydock/lease/{$id}/logs/{$path}";
    }

    throw new Exception(
      pht(
        'Search engine has no blueprint, resource, or lease.'));
  }

  protected function getBuiltinQueryNames() {
    return array(
      'all' => pht('All Logs'),
    );
  }

  public function buildSavedQueryFromBuiltin($query_key) {
    $query = $this->newSavedQuery();
    $query->setQueryKey($query_key);

    switch ($query_key) {
      case 'all':
        return $query;
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

  protected function renderResultList(
    array $logs,
    PhabricatorSavedQuery $query,
    array $handles) {

    $list = id(new DrydockLogListView())
      ->setUser($this->requireViewer())
      ->setLogs($logs);

    $result = new PhabricatorApplicationSearchResultView();
    $result->setTable($list);

    return $result;
  }

}
