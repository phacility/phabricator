<?php

final class PhabricatorSearchApplicationSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function buildSavedQueryFromRequest(AphrontRequest $request) {
    $saved = new PhabricatorSavedQuery();

    return $saved;
  }

  public function buildQueryFromSavedQuery(PhabricatorSavedQuery $saved) {
    $query = id(new PhabricatorSearchDocumentQuery());

    return $query;
  }

  public function buildSearchForm(
    AphrontFormView $form,
    PhabricatorSavedQuery $saved_query) {
    return;
  }

  protected function getURI($path) {
    return '/search/'.$path;
  }

  public function getBuiltinQueryNames() {
    $names = array(
      'all' => pht('All Documents'),
    );

    return $names;
  }

  public function buildSavedQueryFromBuiltin($query_key) {

    $query = $this->newSavedQuery();
    $query->setQueryKey($query_key);

    switch ($query_key) {
      case 'all':
        return $query;
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

}
