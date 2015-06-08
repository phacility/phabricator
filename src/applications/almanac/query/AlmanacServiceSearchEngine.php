<?php

final class AlmanacServiceSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Almanac Services');
  }

  public function getApplicationClassName() {
    return 'PhabricatorAlmanacApplication';
  }

  public function newQuery() {
    return new AlmanacServiceQuery();
  }

  public function newResultObject() {
    // NOTE: We need to attach a service type in order to generate custom
    // field definitions.
    return AlmanacService::initializeNewService()
      ->attachServiceType(new AlmanacCustomServiceType());
  }

  protected function buildQueryFromParameters(array $map) {
    $query = $this->newQuery();

    return $query;
  }


  protected function buildCustomSearchFields() {
    return array();
  }

  protected function getURI($path) {
    return '/almanac/service/'.$path;
  }

  protected function getBuiltinQueryNames() {
    $names = array(
      'all' => pht('All Services'),
    );

    return $names;
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
    array $services,
    PhabricatorSavedQuery $query,
    array $handles) {
    assert_instances_of($services, 'AlmanacService');

    $viewer = $this->requireViewer();

    $list = new PHUIObjectItemListView();
    $list->setUser($viewer);
    foreach ($services as $service) {
      $item = id(new PHUIObjectItemView())
        ->setObjectName(pht('Service %d', $service->getID()))
        ->setHeader($service->getName())
        ->setHref($service->getURI())
        ->setObject($service)
        ->addIcon(
          $service->getServiceType()->getServiceTypeIcon(),
          $service->getServiceType()->getServiceTypeShortName());

      if ($service->getIsLocked() ||
          $service->getServiceType()->isClusterServiceType()) {
        if ($service->getIsLocked()) {
          $item->addIcon('fa-lock', pht('Locked'));
        } else {
          $item->addIcon('fa-unlock-alt red', pht('Unlocked'));
        }
      }

      $list->addItem($item);
    }

    return $list;
  }
}
