<?php

final class AlmanacDeviceSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Almanac Devices');
  }

  public function getApplicationClassName() {
    return 'PhabricatorAlmanacApplication';
  }

  public function buildSavedQueryFromRequest(AphrontRequest $request) {
    $saved = new PhabricatorSavedQuery();

    return $saved;
  }

  public function buildQueryFromSavedQuery(PhabricatorSavedQuery $saved) {
    $query = id(new AlmanacDeviceQuery());

    return $query;
  }

  public function buildSearchForm(
    AphrontFormView $form,
    PhabricatorSavedQuery $saved_query) {}

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

  protected function getRequiredHandlePHIDsForResultList(
    array $devices,
    PhabricatorSavedQuery $query) {
    return array();
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

      $list->addItem($item);
    }

    return $list;
  }

}
