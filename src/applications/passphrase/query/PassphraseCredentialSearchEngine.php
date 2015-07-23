<?php

final class PassphraseCredentialSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Passphrase Credentials');
  }

  public function getApplicationClassName() {
    return 'PhabricatorPassphraseApplication';
  }

  public function newQuery() {
    return new PassphraseCredentialQuery();
  }

  protected function buildCustomSearchFields() {
    return array(
      id(new PhabricatorSearchThreeStateField())
        ->setLabel(pht('Status'))
        ->setKey('isDestroyed')
        ->setOptions(
          pht('Show All'),
          pht('Show Only Destroyed Credentials'),
          pht('Show Only Active Credentials')),
      id(new PhabricatorSearchTextField())
        ->setLabel(pht('Name Contains'))
        ->setKey('name'),
    );
  }

  protected function buildQueryFromParameters(array $map) {
    $query = $this->newQuery();

    if ($map['isDestroyed'] !== null) {
      $query->withIsDestroyed($map['isDestroyed']);
    }

    if (strlen($map['name'])) {
      $query->withNameContains($map['name']);
    }

    return $query;
  }

  protected function getURI($path) {
    return '/passphrase/'.$path;
  }

  protected function getBuiltinQueryNames() {
    return array(
      'active' => pht('Active Credentials'),
      'all' => pht('All Credentials'),
    );
  }

  public function buildSavedQueryFromBuiltin($query_key) {
    $query = $this->newSavedQuery();
    $query->setQueryKey($query_key);

    switch ($query_key) {
      case 'all':
        return $query;
      case 'active':
        return $query->setParameter('isDestroyed', false);
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

  protected function renderResultList(
    array $credentials,
    PhabricatorSavedQuery $query,
    array $handles) {
    assert_instances_of($credentials, 'PassphraseCredential');

    $viewer = $this->requireViewer();

    $list = new PHUIObjectItemListView();
    $list->setUser($viewer);
    foreach ($credentials as $credential) {

      $item = id(new PHUIObjectItemView())
        ->setObjectName('K'.$credential->getID())
        ->setHeader($credential->getName())
        ->setHref('/K'.$credential->getID())
        ->setObject($credential);

      $item->addAttribute(
        pht('Login: %s', $credential->getUsername()));

      if ($credential->getIsDestroyed()) {
        $item->addIcon('fa-ban', pht('Destroyed'));
        $item->setDisabled(true);
      }

      $type = PassphraseCredentialType::getTypeByConstant(
        $credential->getCredentialType());
      if ($type) {
        $item->addIcon('fa-wrench', $type->getCredentialTypeName());
      }

      $list->addItem($item);
    }

    $result = new PhabricatorApplicationSearchResultView();
    $result->setObjectList($list);
    $result->setNoDataString(pht('No credentials found.'));

    return $result;
  }

}
