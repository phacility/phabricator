<?php

final class PhabricatorDashboardPortalSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Portals');
  }

  public function getApplicationClassName() {
    return 'PhabricatorDashboardApplication';
  }

  public function newQuery() {
    return new PhabricatorDashboardPortalQuery();
  }

  protected function buildQueryFromParameters(array $map) {
    $query = $this->newQuery();
    return $query;
  }

  protected function buildCustomSearchFields() {
    return array();
  }

  protected function getURI($path) {
    return '/portal/'.$path;
  }

  protected function getBuiltinQueryNames() {
    $names = array();

    $names['all'] = pht('All Portals');

    return $names;
  }

  public function buildSavedQueryFromBuiltin($query_key) {
    $query = $this->newSavedQuery();
    $query->setQueryKey($query_key);
    $viewer = $this->requireViewer();

    switch ($query_key) {
      case 'all':
        return $query;
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

  protected function renderResultList(
    array $portals,
    PhabricatorSavedQuery $query,
    array $handles) {

    assert_instances_of($portals, 'PhabricatorDashboardPortal');

    $viewer = $this->requireViewer();

    $list = new PHUIObjectItemListView();
    $list->setUser($viewer);
    foreach ($portals as $portal) {
      $item = id(new PHUIObjectItemView())
        ->setObjectName($portal->getObjectName())
        ->setHeader($portal->getName())
        ->setHref($portal->getURI())
        ->setObject($portal);

      $list->addItem($item);
    }

    return id(new PhabricatorApplicationSearchResultView())
      ->setObjectList($list)
      ->setNoDataString(pht('No portals found.'));
  }

}
