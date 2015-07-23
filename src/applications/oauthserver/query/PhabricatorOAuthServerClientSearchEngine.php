<?php

final class PhabricatorOAuthServerClientSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('OAuth Clients');
  }

  public function getApplicationClassName() {
    return 'PhabricatorOAuthServerApplication';
  }

  public function buildSavedQueryFromRequest(AphrontRequest $request) {
    $saved = new PhabricatorSavedQuery();

    $saved->setParameter(
      'creatorPHIDs',
      $this->readUsersFromRequest($request, 'creators'));

    return $saved;
  }

  public function buildQueryFromSavedQuery(PhabricatorSavedQuery $saved) {
    $query = id(new PhabricatorOAuthServerClientQuery());

    $creator_phids = $saved->getParameter('creatorPHIDs', array());
    if ($creator_phids) {
      $query->withCreatorPHIDs($saved->getParameter('creatorPHIDs', array()));
    }

    return $query;
  }

  public function buildSearchForm(
    AphrontFormView $form,
    PhabricatorSavedQuery $saved_query) {

    $creator_phids = $saved_query->getParameter('creatorPHIDs', array());

    $form
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setDatasource(new PhabricatorPeopleDatasource())
          ->setName('creators')
          ->setLabel(pht('Creators'))
          ->setValue($creator_phids));
  }

  protected function getURI($path) {
    return '/oauthserver/'.$path;
  }

  protected function getBuiltinQueryNames() {
    $names = array();

    if ($this->requireViewer()->isLoggedIn()) {
      $names['created'] = pht('Created');
    }

    $names['all'] = pht('All Applications');

    return $names;
  }

  public function buildSavedQueryFromBuiltin($query_key) {
    $query = $this->newSavedQuery();
    $query->setQueryKey($query_key);

    switch ($query_key) {
      case 'all':
        return $query;
      case 'created':
        return $query->setParameter(
          'creatorPHIDs',
          array($this->requireViewer()->getPHID()));
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

  protected function getRequiredHandlePHIDsForResultList(
    array $clients,
    PhabricatorSavedQuery $query) {
    return mpull($clients, 'getCreatorPHID');
  }

  protected function renderResultList(
    array $clients,
    PhabricatorSavedQuery $query,
    array $handles) {
    assert_instances_of($clients, 'PhabricatorOauthServerClient');

    $viewer = $this->requireViewer();

    $list = id(new PHUIObjectItemListView())
      ->setUser($viewer);
    foreach ($clients as $client) {
      $creator = $handles[$client->getCreatorPHID()];

      $item = id(new PHUIObjectItemView())
        ->setObjectName(pht('Application %d', $client->getID()))
        ->setHeader($client->getName())
        ->setHref($client->getViewURI())
        ->setObject($client)
        ->addByline(pht('Creator: %s', $creator->renderLink()));

      $list->addItem($item);
    }

    $result = new PhabricatorApplicationSearchResultView();
    $result->setObjectList($list);
    $result->setNoDataString(pht('No clients found.'));

    return $result;
  }

}
