<?php

final class PassphraseCredentialSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Passphrase Credentials');
  }

  public function getApplicationClassName() {
    return 'PhabricatorPassphraseApplication';
  }

  public function buildSavedQueryFromRequest(AphrontRequest $request) {
    $saved = new PhabricatorSavedQuery();

    $saved->setParameter(
      'isDestroyed',
      $this->readBoolFromRequest($request, 'isDestroyed'));
    $saved->setParameter('name', $request->getStr('name'));

    return $saved;
  }

  public function buildQueryFromSavedQuery(PhabricatorSavedQuery $saved) {
    $query = id(new PassphraseCredentialQuery());

    $destroyed = $saved->getParameter('isDestroyed');
    if ($destroyed !== null) {
      $query->withIsDestroyed($destroyed);
    }

    $name = $saved->getParameter('name');
    if (strlen($name)) {
      $query->withNameContains($name);
    }

    return $query;
  }

  public function buildSearchForm(
    AphrontFormView $form,
    PhabricatorSavedQuery $saved_query) {

    $name = $saved_query->getParameter('name');

    $form
      ->appendChild(
      id(new AphrontFormSelectControl())
        ->setName('isDestroyed')
        ->setLabel(pht('Status'))
        ->setValue($this->getBoolFromQuery($saved_query, 'isDestroyed'))
        ->setOptions(
          array(
            '' => pht('Show All Credentials'),
            'false' => pht('Show Only Active Credentials'),
            'true' => pht('Show Only Destroyed Credentials'),
          )))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setName('name')
          ->setLabel(pht('Name Contains'))
          ->setValue($name));
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

    return $list;
  }

}
