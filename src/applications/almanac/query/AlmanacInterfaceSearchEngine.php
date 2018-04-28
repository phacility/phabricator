<?php

final class AlmanacInterfaceSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Almanac Interfaces');
  }

  public function getApplicationClassName() {
    return 'PhabricatorAlmanacApplication';
  }

  public function newQuery() {
    return new AlmanacInterfaceQuery();
  }

  protected function buildCustomSearchFields() {
    return array(
      id(new PhabricatorPHIDsSearchField())
        ->setLabel(pht('Devices'))
        ->setKey('devicePHIDs')
        ->setAliases(array('device', 'devicePHID', 'devices'))
        ->setDescription(pht('Search for interfaces on particular devices.')),
    );
  }

  protected function buildQueryFromParameters(array $map) {
    $query = $this->newQuery();

    if ($map['devicePHIDs']) {
      $query->withDevicePHIDs($map['devicePHIDs']);
    }

    return $query;
  }

  protected function getURI($path) {
    return '/almanac/interface/'.$path;
  }

  protected function getBuiltinQueryNames() {
    $names = array(
      'all' => pht('All Interfaces'),
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

    // For now, this SearchEngine just supports API access via Conduit.
    throw new PhutilMethodNotImplementedException();
  }

}
