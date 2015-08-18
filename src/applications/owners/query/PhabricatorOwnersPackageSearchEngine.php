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
        ->setLabel(pht('Authority'))
        ->setKey('authorityPHIDs')
        ->setAliases(array('authority', 'authorities'))
        ->setDatasource(new PhabricatorProjectOrUserDatasource()),
      id(new PhabricatorSearchDatasourceField())
        ->setLabel(pht('Repositories'))
        ->setKey('repositoryPHIDs')
        ->setAliases(array('repository', 'repositories'))
        ->setDatasource(new DiffusionRepositoryDatasource()),
      id(new PhabricatorSearchStringListField())
        ->setLabel(pht('Paths'))
        ->setKey('paths')
        ->setAliases(array('path')),
      id(new PhabricatorSearchCheckboxesField())
        ->setKey('statuses')
        ->setLabel(pht('Status'))
        ->setOptions(
          id(new PhabricatorOwnersPackage())
            ->getStatusNameMap()),
    );
  }

  protected function buildQueryFromParameters(array $map) {
    $query = $this->newQuery();

    if ($map['authorityPHIDs']) {
      $query->withAuthorityPHIDs($map['authorityPHIDs']);
    }

    if ($map['repositoryPHIDs']) {
      $query->withRepositoryPHIDs($map['repositoryPHIDs']);
    }

    if ($map['paths']) {
      $query->withPaths($map['paths']);
    }

    if ($map['statuses']) {
      $query->withStatuses($map['statuses']);
    }

    return $query;
  }

  protected function getURI($path) {
    return '/owners/'.$path;
  }

  protected function getBuiltinQueryNames() {
    $names = array();

    if ($this->requireViewer()->isLoggedIn()) {
      $names['authority'] = pht('Owned');
    }

    $names += array(
      'active' => pht('Active Packages'),
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
      case 'active':
        return $query->setParameter(
          'statuses',
          array(
            PhabricatorOwnersPackage::STATUS_ACTIVE,
          ));
      case 'authority':
        return $query->setParameter(
          'authorityPHIDs',
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

      if ($package->isArchived()) {
        $item->setDisabled(true);
      }

      $list->addItem($item);
    }

    $result = new PhabricatorApplicationSearchResultView();
    $result->setObjectList($list);
    $result->setNoDataString(pht('No packages found.'));

    return $result;

  }
}
