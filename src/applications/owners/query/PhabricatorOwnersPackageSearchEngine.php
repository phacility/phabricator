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
        ->setConduitKey('owners')
        ->setDescription(
          pht('Search for packages with specific owners.'))
        ->setDatasource(new PhabricatorProjectOrUserDatasource()),
      id(new PhabricatorSearchTextField())
        ->setLabel(pht('Name Contains'))
        ->setKey('name')
        ->setDescription(pht('Search for packages by name substrings.')),
      id(new PhabricatorSearchDatasourceField())
        ->setLabel(pht('Repositories'))
        ->setKey('repositoryPHIDs')
        ->setConduitKey('repositories')
        ->setAliases(array('repository', 'repositories'))
        ->setDescription(
          pht('Search for packages by included repositories.'))
        ->setDatasource(new DiffusionRepositoryDatasource()),
      id(new PhabricatorSearchStringListField())
        ->setLabel(pht('Paths'))
        ->setKey('paths')
        ->setAliases(array('path'))
        ->setDescription(
          pht('Search for packages affecting specific paths.')),
      id(new PhabricatorSearchCheckboxesField())
        ->setKey('statuses')
        ->setLabel(pht('Status'))
        ->setDescription(
          pht('Search for active or archived packages.'))
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

    if (strlen($map['name'])) {
      $query->withNameNgrams($map['name']);
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
        ->setObjectName($package->getMonogram())
        ->setHeader($package->getName())
        ->setHref($package->getURI());

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

  protected function getNewUserBody() {
    $create_button = id(new PHUIButtonView())
      ->setTag('a')
      ->setText(pht('Create a Package'))
      ->setHref('/owners/edit/')
      ->setColor(PHUIButtonView::GREEN);

    $icon = $this->getApplication()->getIcon();
    $app_name =  $this->getApplication()->getName();
    $view = id(new PHUIBigInfoView())
      ->setIcon($icon)
      ->setTitle(pht('Welcome to %s', $app_name))
      ->setDescription(
        pht('Group sections of a codebase into packages for re-use in other '.
        'areas of Phabricator, like Herald rules.'))
      ->addAction($create_button);

      return $view;
  }

}
