<?php

final class PhabricatorOwnersPackageSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Owners Packages');
  }

  public function getApplicationClassName() {
    return 'PhabricatorOwnersApplication';
  }

  public function buildSavedQueryFromRequest(AphrontRequest $request) {
    $saved = new PhabricatorSavedQuery();

    $saved->setParameter(
      'ownerPHIDs',
      $this->readUsersFromRequest(
        $request,
        'owners',
        array(
          PhabricatorProjectProjectPHIDType::TYPECONST,
        )));

    $saved->setParameter(
      'repositoryPHIDs',
      $this->readPHIDsFromRequest(
        $request,
        'repositories',
        array(
          PhabricatorRepositoryRepositoryPHIDType::TYPECONST,
        )));

    return $saved;
  }

  public function buildQueryFromSavedQuery(PhabricatorSavedQuery $saved) {
    $query = id(new PhabricatorOwnersPackageQuery());

    $owner_phids = $saved->getParameter('ownerPHIDs', array());
    if ($owner_phids) {
      $query->withOwnerPHIDs($owner_phids);
    }

    $repository_phids = $saved->getParameter('repositoryPHIDs', array());
    if ($repository_phids) {
      $query->withRepositoryPHIDs($repository_phids);
    }

    return $query;
  }

  public function buildSearchForm(
    AphrontFormView $form,
    PhabricatorSavedQuery $saved) {

    $owner_phids = $saved->getParameter('ownerPHIDs', array());
    $repository_phids = $saved->getParameter('repositoryPHIDs', array());

    $form
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setDatasource(new PhabricatorProjectOrUserDatasource())
          ->setName('owners')
          ->setLabel(pht('Owners'))
          ->setValue($owner_phids))
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setDatasource(new DiffusionRepositoryDatasource())
          ->setName('repositories')
          ->setLabel(pht('Repositories'))
          ->setValue($repository_phids));
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
