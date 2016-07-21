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

    return $query;
  }

  protected function buildCustomSearchFields() {
    return array();
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

    $list = id(new PHUIObjectItemListView())
      ->setViewer($viewer);
    foreach ($packages as $package) {
      $item = id(new PHUIObjectItemView())
        ->setObjectName($package->getFullKey())
        ->setHeader($package->getName())
        ->setHref($package->getURI());

      $list->addItem($item);
    }

    return id(new PhabricatorApplicationSearchResultView())
      ->setObjectList($list)
      ->setNoDataString(pht('No packages found.'));
  }

}
