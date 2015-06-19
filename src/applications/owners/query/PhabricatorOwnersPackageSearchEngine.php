<?php

final class PhabricatorOwnersPackageSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Owners Packages');
  }

  public function getApplicationClassName() {
    return 'PhabricatorOwnersApplication';
  }

  public function newQuery() {
    return new PhabricatorOwnersPackageQuery();
  }

  protected function buildCustomSearchFields() {
    return array(
      id(new PhabricatorSearchDatasourceField())
        ->setLabel(pht('Owners'))
        ->setKey('ownerPHIDs')
        ->setAliases(array('owner', 'owners'))
        ->setDatasource(new PhabricatorProjectOrUserDatasource()),
      id(new PhabricatorSearchDatasourceField())
        ->setLabel(pht('Repositories'))
        ->setKey('repositoryPHIDs')
        ->setAliases(array('repository', 'repositories'))
        ->setDatasource(new DiffusionRepositoryDatasource()),
    );
  }

  protected function buildQueryFromParameters(array $map) {
    $query = $this->newQuery();

    if ($map['ownerPHIDs']) {
      $query->withOwnerPHIDs($map['ownerPHIDs']);
    }

    if ($map['repositoryPHIDs']) {
      $query->withRepositoryPHIDs($map['repositoryPHIDs']);
    }

    return $query;
  }

  protected function getURI($path) {
    return '/owners/'.$path;
  }

  protected function getBuiltinQueryNames() {
    $names = array();

    if ($this->requireViewer()->isLoggedIn()) {
      $names['owned'] = pht('Owned');
    }

    $names += array(
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
      case 'owned':
        return $query->setParameter(
          'ownerPHIDs',
          array($this->requireViewer()->getPHID()));
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

  protected function renderResultList(
    array $packages,
    PhabricatorSavedQuery $query,
    array $handles) {
    assert_instances_of($packages, 'PhabricatorOwnersPackage');

    $viewer = $this->requireViewer();

    $list = id(new PHUIObjectItemListView())
      ->setUser($viewer);
    foreach ($packages as $package) {
      $id = $package->getID();

      $item = id(new PHUIObjectItemView())
        ->setObject($package)
        ->setObjectName(pht('Package %d', $id))
        ->setHeader($package->getName())
        ->setHref('/owners/package/'.$id.'/');

      $list->addItem($item);
    }

    return $list;
  }
}
