<?php

final class PhabricatorRepositorySearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function buildSavedQueryFromRequest(AphrontRequest $request) {
    $saved = new PhabricatorSavedQuery();

    $saved->setParameter('callsigns', $request->getStrList('callsigns'));

    return $saved;
  }

  public function buildQueryFromSavedQuery(PhabricatorSavedQuery $saved) {
    $query = id(new PhabricatorRepositoryQuery())
      ->needCommitCounts(true)
      ->needMostRecentCommits(true);

    $callsigns = $saved->getParameter('callsigns');
    if ($callsigns) {
      $query->withCallsigns($callsigns);
    }

    return $query;
  }

  public function buildSearchForm(
    AphrontFormView $form,
    PhabricatorSavedQuery $saved_query) {

    $callsigns = $saved_query->getParameter('callsigns', array());

    $form
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setName('callsigns')
          ->setLabel(pht('Callsigns'))
          ->setValue(implode(', ', $callsigns)));
  }

  protected function getURI($path) {
    return '/diffusion/'.$path;
  }

  public function getBuiltinQueryNames() {
    $names = array(
      'all' => pht('All Repositories'),
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
