<?php

final class PhabricatorPackagesVersionSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Package Versions');
  }

  public function getApplicationClassName() {
    return 'PhabricatorPackagesApplication';
  }

  public function newQuery() {
    return id(new PhabricatorPackagesVersionQuery());
  }

  protected function buildQueryFromParameters(array $map) {
    $query = $this->newQuery();

    if ($map['match'] !== null) {
      $query->withNameNgrams($map['match']);
    }

    if ($map['packagePHIDs']) {
      $query->withPackagePHIDs($map['packagePHIDs']);
    }

    return $query;
  }

  protected function buildCustomSearchFields() {
    return array(
      id(new PhabricatorSearchTextField())
        ->setLabel(pht('Name Contains'))
        ->setKey('match')
        ->setDescription(pht('Search for versions by name substring.')),
      id(new PhabricatorSearchDatasourceField())
        ->setLabel(pht('Packages'))
        ->setKey('packagePHIDs')
        ->setAliases(array('packagePHID', 'package', 'packages'))
        ->setDatasource(new PhabricatorPackagesPackageDatasource())
        ->setDescription(pht('Search for versions by package.')),
    );
  }
  protected function getURI($path) {
    return '/packages/version/'.$path;
  }

  protected function getBuiltinQueryNames() {
    $names = array(
      'all' => pht('All Versions'),
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
    array $versions,
    PhabricatorSavedQuery $query,
    array $handles) {

    assert_instances_of($versions, 'PhabricatorPackagesVersion');
    $viewer = $this->requireViewer();

    $list = id(new PhabricatorPackagesVersionListView())
      ->setViewer($viewer)
      ->setVersions($versions)
      ->newListView();

    return id(new PhabricatorApplicationSearchResultView())
      ->setObjectList($list)
      ->setNoDataString(pht('No versions found.'));
  }

}
