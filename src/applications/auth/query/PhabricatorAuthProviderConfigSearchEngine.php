<?php

final class PhabricatorAuthProviderConfigSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function buildSavedQueryFromRequest(AphrontRequest $request) {
    $saved = new PhabricatorSavedQuery();
    $saved->setParameter('status', $request->getStr('status'));
    return $saved;
  }

  public function buildQueryFromSavedQuery(PhabricatorSavedQuery $saved) {
    $query = new PhabricatorAuthProviderConfigQuery();

    $status = $saved->getParameter('status');
    $options = PhabricatorAuthProviderConfigQuery::getStatusOptions();
    if (empty($options[$status])) {
      $status = head_key($options);
    }
    $query->withStatus($status);

    return $query;
  }

  public function buildSearchForm(
    AphrontFormView $form,
    PhabricatorSavedQuery $saved_query) {

    $status = $saved_query->getParameter('status');

    $form
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setName('status')
          ->setLabel(pht('Status'))
          ->setOptions(PhabricatorAuthProviderConfigQuery::getStatusOptions())
          ->setValue($status));
  }

  protected function getURI($path) {
    return '/auth/'.$path;
  }

  public function getBuiltinQueryNames() {
    $names = array(
      'all'     => pht('All'),
    );

    return $names;
  }

  public function buildSavedQueryFromBuiltin($query_key) {
    $query = $this->newSavedQuery();
    $query->setQueryKey($query_key);

    switch ($query_key) {
      case 'all':
        return $query->setParameter(
          'status',
          PhabricatorAuthProviderConfigQuery::STATUS_ALL);
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

}
