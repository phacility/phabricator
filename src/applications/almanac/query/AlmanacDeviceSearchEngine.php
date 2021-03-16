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
    $status_options = AlmanacDeviceStatus::getStatusMap();
    $status_options = mpull($status_options, 'getName');

    return array(
      id(new PhabricatorSearchTextField())
        ->setLabel(pht('Name Contains'))
        ->setKey('match')
        ->setDescription(pht('Search for devices by name substring.')),
      id(new PhabricatorSearchStringListField())
        ->setLabel(pht('Exact Names'))
        ->setKey('names')
        ->setDescription(pht('Search for devices with specific names.')),
      id(new PhabricatorSearchCheckboxesField())
        ->setLabel(pht('Statuses'))
        ->setKey('statuses')
        ->setDescription(pht('Search for devices with given statuses.'))
        ->setOptions($status_options),
      id(new PhabricatorSearchThreeStateField())
        ->setLabel(pht('Cluster Device'))
        ->setKey('isClusterDevice')
        ->setOptions(
          pht('Both Cluster and Non-cluster Devices'),
          pht('Cluster Devices Only'),
          pht('Non-cluster Devices Only')),
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

    if ($map['isClusterDevice'] !== null) {
      $query->withIsClusterDevice($map['isClusterDevice']);
    }

    if ($map['statuses']) {
      $query->withStatuses($map['statuses']);
    }

    return $query;
  }

  protected function getURI($path) {
    return '/almanac/device/'.$path;
  }

  protected function getBuiltinQueryNames() {
    $names = array(
      'active' => pht('Active Devices'),
      'all' => pht('All Devices'),
    );

    return $names;
  }

  public function buildSavedQueryFromBuiltin($query_key) {
    $query = $this->newSavedQuery();
    $query->setQueryKey($query_key);

    switch ($query_key) {
      case 'active':
        $active_statuses = AlmanacDeviceStatus::getActiveStatusList();
        return $query->setParameter('statuses', $active_statuses);
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

      if ($device->isDisabled()) {
        $item->setDisabled(true);
      }

      $status = $device->getStatusObject();
      $icon_icon = $status->getIconIcon();
      $icon_color = $status->getIconColor();
      $icon_label = $status->getName();

      $item->setStatusIcon(
        "{$icon_icon} {$icon_color}",
        $icon_label);

      $list->addItem($item);
    }

    $result = new PhabricatorApplicationSearchResultView();
    $result->setObjectList($list);
    $result->setNoDataString(pht('No Almanac Devices found.'));

    return $result;
  }

}
