<?php

final class PhabricatorPackagesPackageSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Packages');
  }

  public function getApplicationClassName() {
    return 'PhabricatorPackagesApplication';
  }

  public function newQuery() {
    return id(new PhabricatorPackagesPackageQuery());
  }

  protected function buildQueryFromParameters(array $map) {
    $query = $this->newQuery();

    if ($map['match'] !== null) {
      $query->withNameNgrams($map['match']);
    }

    if ($map['publisherPHIDs']) {
      $query->withPublisherPHIDs($map['publisherPHIDs']);
    }

    return $query;
  }

  protected function buildCustomSearchFields() {
    return array(
      id(new PhabricatorSearchTextField())
        ->setLabel(pht('Name Contains'))
        ->setKey('match')
        ->setDescription(pht('Search for packages by name substring.')),
      id(new PhabricatorSearchDatasourceField())
        ->setLabel(pht('Publishers'))
        ->setKey('publisherPHIDs')
        ->setAliases(array('publisherPHID', 'publisher', 'publishers'))
        ->setDatasource(new PhabricatorPackagesPublisherDatasource())
        ->setDescription(pht('Search for packages by publisher.')),
    );
  }

  protected function getURI($path) {
    return '/packages/package/'.$path;
  }

  protected function getBuiltinQueryNames() {
    $names = array(
      'all' => pht('All Packages'),
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
    array $packages,
    PhabricatorSavedQuery $query,
    array $handles) {

    assert_instances_of($packages, 'PhabricatorPackagesPackage');
    $viewer = $this->requireViewer();

    $list = id(new PhabricatorPackagesPackageListView())
      ->setViewer($viewer)
      ->setPackages($packages)
      ->newListView();

    return id(new PhabricatorApplicationSearchResultView())
      ->setObjectList($list)
      ->setNoDataString(pht('No packages found.'));
  }

}
