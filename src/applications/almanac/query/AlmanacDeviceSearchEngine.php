<?php

final class AlmanacDeviceSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Almanac Devices');
  }

  public function getApplicationClassName() {
    return 'PhabricatorAlmanacApplication';
  }

  public function newQuery() {
    return new AlmanacDeviceQuery();
  }

  protected function buildCustomSearchFields() {
    return array(
      id(new PhabricatorSearchTextField())
        ->setLabel(pht('Name Contains'))
        ->setKey('match')
        ->setDescription(pht('Search for devices by name substring.')),
      id(new PhabricatorSearchStringListField())
        ->setLabel(pht('Exact Names'))
        ->setKey('names')
        ->setDescription(pht('Search for devices with specific names.')),
    );
  }

  protected function buildQueryFromParameters(array $map) {
    $query = $this->newQuery();

    if ($map['match'] !== null) {
      $query->withNameNgrams($map['match']);
    }

    if ($map['names']) {
      $query->withNames($map['names']);
    }

    return $query;
  }

  protected function getURI($path) {
    return '/almanac/device/'.$path;
  }

  protected function getBuiltinQueryNames() {
    $names = array(
      'all' => pht('All Devices'),
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
    array $devices,
    PhabricatorSavedQuery $query,
    array $handles) {
    assert_instances_of($devices, 'AlmanacDevice');

    $viewer = $this->requireViewer();

    $list = new PHUIObjectItemListView();
    $list->setUser($viewer);
    foreach ($devices as $device) {
      $item = id(new PHUIObjectItemView())
        ->setObjectName(pht('Device %d', $device->getID()))
        ->setHeader($device->getName())
        ->setHref($device->getURI())
        ->setObject($device);

      if ($device->isClusterDevice()) {
        $item->addIcon('fa-sitemap', pht('Cluster Device'));
      }

      $list->addItem($item);
    }

    $result = new PhabricatorApplicationSearchResultView();
    $result->setObjectList($list);
    $result->setNoDataString(pht('No Almanac Devices found.'));

    return $result;
  }

}
