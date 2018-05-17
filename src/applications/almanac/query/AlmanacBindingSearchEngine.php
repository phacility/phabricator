<?php

final class AlmanacBindingSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Almanac Bindings');
  }

  public function getApplicationClassName() {
    return 'PhabricatorAlmanacApplication';
  }

  public function newQuery() {
    return new AlmanacBindingQuery();
  }

  protected function buildCustomSearchFields() {
    return array(
      id(new PhabricatorPHIDsSearchField())
        ->setLabel(pht('Services'))
        ->setKey('servicePHIDs')
        ->setAliases(array('service', 'servicePHID', 'services'))
        ->setDescription(pht('Search for bindings on particular services.')),
      id(new PhabricatorPHIDsSearchField())
        ->setLabel(pht('Devices'))
        ->setKey('devicePHIDs')
        ->setAliases(array('device', 'devicePHID', 'devices'))
        ->setDescription(pht('Search for bindings on particular devices.')),
    );
  }

  protected function buildQueryFromParameters(array $map) {
    $query = $this->newQuery();

    if ($map['servicePHIDs']) {
      $query->withServicePHIDs($map['servicePHIDs']);
    }

    if ($map['devicePHIDs']) {
      $query->withDevicePHIDs($map['devicePHIDs']);
    }

    return $query;
  }

  protected function getURI($path) {
    return '/almanac/binding/'.$path;
  }

  protected function getBuiltinQueryNames() {
    $names = array(
      'all' => pht('All Bindings'),
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
