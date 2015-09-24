<?php

final class DrydockResourceSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Drydock Resources');
  }

  public function getApplicationClassName() {
    return 'PhabricatorDrydockApplication';
  }

  public function newQuery() {
    return new DrydockResourceQuery();
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
        ->setOptions(DrydockResourceStatus::getStatusMap()),
    );
  }

  protected function getURI($path) {
    return '/drydock/resource/'.$path;
  }

  protected function getBuiltinQueryNames() {
    return array(
      'active' => pht('Active Resources'),
      'all' => pht('All Resources'),
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
            DrydockResourceStatus::STATUS_PENDING,
            DrydockResourceStatus::STATUS_ACTIVE,
          ));
      case 'all':
        return $query;
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

  protected function renderResultList(
    array $resources,
    PhabricatorSavedQuery $query,
    array $handles) {

    $list = id(new DrydockResourceListView())
      ->setUser($this->requireViewer())
      ->setResources($resources);

    $result = new PhabricatorApplicationSearchResultView();
    $result->setTable($list);

    return $result;
  }

}
