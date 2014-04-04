<?php

final class PassphraseCredentialSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function buildSavedQueryFromRequest(AphrontRequest $request) {
    $saved = new PhabricatorSavedQuery();

    $saved->setParameter(
      'isDestroyed',
      $this->readBoolFromRequest($request, 'isDestroyed'));

    return $saved;
  }

  public function buildQueryFromSavedQuery(PhabricatorSavedQuery $saved) {
    $query = id(new PassphraseCredentialQuery());

    $destroyed = $saved->getParameter('isDestroyed');
    if ($destroyed !== null) {
      $query->withIsDestroyed($destroyed);
    }

    return $query;
  }

  public function buildSearchForm(
    AphrontFormView $form,
    PhabricatorSavedQuery $saved_query) {

    $form->appendChild(
      id(new AphrontFormSelectControl())
        ->setName('isDestroyed')
        ->setLabel(pht('Status'))
        ->setValue($this->getBoolFromQuery($saved_query, 'isDestroyed'))
        ->setOptions(
          array(
            '' => pht('Show All Credentials'),
            'false' => pht('Show Only Active Credentials'),
            'true' => pht('Show Only Destroyed Credentials'),
          )));

  }

  protected function getURI($path) {
    return '/passphrase/'.$path;
  }

  public function getBuiltinQueryNames() {
    $names = array(
      'active' => pht('Active Credentials'),
      'all' => pht('All Credentials'),
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
        return $query->setParameter('isDestroyed', false);
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

}
