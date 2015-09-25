<?php

final class DrydockLeaseSearchEngine
  extends PhabricatorApplicationSearchEngine {

  private $resource;

  public function setResource($resource) {
    $this->resource = $resource;
    return $this;
  }

  public function getResource() {
    return $this->resource;
  }

  public function getResultTypeDescription() {
    return pht('Drydock Leases');
  }

  public function getApplicationClassName() {
    return 'PhabricatorDrydockApplication';
  }

  public function newQuery() {
    $query = new DrydockLeaseQuery();

    $resource = $this->getResource();
    if ($resource) {
      $query->withResourcePHIDs(array($resource->getPHID()));
    }

    return $query;
  }

  protected function buildQueryFromParameters(array $map) {
    $query = $this->newQuery();

    if ($map['statuses']) {
      $query->withStatuses($map['statuses']);
    }

    return $query;
  }

  protected function buildCustomSearchFields() {
    return array(
      id(new PhabricatorSearchCheckboxesField())
        ->setLabel(pht('Statuses'))
        ->setKey('statuses')
        ->setOptions(DrydockLeaseStatus::getStatusMap()),
    );
  }

  protected function getURI($path) {
    $resource = $this->getResource();
    if ($resource) {
      $id = $resource->getID();
      return "/drydock/resource/{$id}/leases/".$path;
    } else {
      return '/drydock/lease/'.$path;
    }
  }

  protected function getBuiltinQueryNames() {
    return array(
      'active' => pht('Active Leases'),
      'all' => pht('All Leases'),
    );
  }

  public function buildSavedQueryFromBuiltin($query_key) {
    $query = $this->newSavedQuery();
    $query->setQueryKey($query_key);

    switch ($query_key) {
      case 'active':
        return $query->setParameter(
          'statuses',
          array(
            DrydockLeaseStatus::STATUS_PENDING,
            DrydockLeaseStatus::STATUS_ACQUIRED,
            DrydockLeaseStatus::STATUS_ACTIVE,
          ));
      case 'all':
        return $query;
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

  protected function renderResultList(
    array $leases,
    PhabricatorSavedQuery $saved,
    array $handles) {

    $list = id(new DrydockLeaseListView())
      ->setUser($this->requireViewer())
      ->setLeases($leases);

    return id(new PhabricatorApplicationSearchResultView())
      ->setContent($list);
  }

}
