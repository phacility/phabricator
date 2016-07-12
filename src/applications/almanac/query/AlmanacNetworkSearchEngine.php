<?php

final class AlmanacNetworkSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Almanac Networks');
  }

  public function getApplicationClassName() {
    return 'PhabricatorAlmanacApplication';
  }

  public function newQuery() {
    return new AlmanacNetworkQuery();
  }

  protected function buildCustomSearchFields() {
    return array(
      id(new PhabricatorSearchTextField())
        ->setLabel(pht('Name Contains'))
        ->setKey('match')
        ->setDescription(pht('Search for networks by name substring.')),
    );
  }

  protected function buildQueryFromParameters(array $map) {
    $query = $this->newQuery();

    if ($map['match'] !== null) {
      $query->withNameNgrams($map['match']);
    }

    return $query;
  }

  protected function getURI($path) {
    return '/almanac/network/'.$path;
  }

  protected function getBuiltinQueryNames() {
    $names = array(
      'all' => pht('All Networks'),
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
    array $networks,
    PhabricatorSavedQuery $query,
    array $handles) {
    assert_instances_of($networks, 'AlmanacNetwork');

    $viewer = $this->requireViewer();

    $list = new PHUIObjectItemListView();
    $list->setUser($viewer);
    foreach ($networks as $network) {
      $id = $network->getID();

      $item = id(new PHUIObjectItemView())
        ->setObjectName(pht('Network %d', $id))
        ->setHeader($network->getName())
        ->setHref($this->getApplicationURI("network/{$id}/"))
        ->setObject($network);

      $list->addItem($item);
    }

    $result = new PhabricatorApplicationSearchResultView();
    $result->setObjectList($list);
    $result->setNoDataString(pht('No Almanac Networks found.'));

    return $result;
  }
}
