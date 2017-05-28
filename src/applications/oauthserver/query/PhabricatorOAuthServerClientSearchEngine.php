<?php

final class PhabricatorOAuthServerClientSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('OAuth Clients');
  }

  public function getApplicationClassName() {
    return 'PhabricatorOAuthServerApplication';
  }

  public function canUseInPanelContext() {
    return false;
  }

  public function newQuery() {
    return id(new PhabricatorOAuthServerClientQuery());
  }

  protected function buildQueryFromParameters(array $map) {
    $query = $this->newQuery();

    if ($map['creatorPHIDs']) {
      $query->withCreatorPHIDs($map['creatorPHIDs']);
    }

    return $query;
  }

  protected function buildCustomSearchFields() {
    return array(
      id(new PhabricatorUsersSearchField())
        ->setAliases(array('creators'))
        ->setKey('creatorPHIDs')
        ->setConduitKey('creators')
        ->setLabel(pht('Creators'))
        ->setDescription(
          pht('Search for applications created by particular users.')),
    );
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

  protected function renderResultList(
    array $clients,
    PhabricatorSavedQuery $query,
    array $handles) {
    assert_instances_of($clients, 'PhabricatorOAuthServerClient');

    $viewer = $this->requireViewer();

    $list = id(new PHUIObjectItemListView())
      ->setUser($viewer);
    foreach ($clients as $client) {
      $item = id(new PHUIObjectItemView())
        ->setObjectName(pht('Application %d', $client->getID()))
        ->setHeader($client->getName())
        ->setHref($client->getViewURI())
        ->setObject($client);

      if ($client->getIsDisabled()) {
        $item->setDisabled(true);
      }

      $list->addItem($item);
    }

    $result = new PhabricatorApplicationSearchResultView();
    $result->setObjectList($list);
    $result->setNoDataString(pht('No clients found.'));

    return $result;
  }

}
