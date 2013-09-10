<?php

final class PhabricatorRepositorySearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function buildSavedQueryFromRequest(AphrontRequest $request) {
    $saved = new PhabricatorSavedQuery();

    $saved->setParameter('callsigns', $request->getStrList('callsigns'));
    $saved->setParameter('status', $request->getStr('status'));

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

    $status = $saved->getParameter('status');
    $status = idx($this->getStatusValues(), $status);
    if ($status) {
      $query->withStatus($status);
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
          ->setValue(implode(', ', $callsigns)))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setName('status')
          ->setLabel(pht('Status'))
          ->setValue($saved_query->getParameter('status'))
          ->setOptions($this->getStatusOptions()));
  }

  protected function getURI($path) {
    return '/diffusion/'.$path;
  }

  public function getBuiltinQueryNames() {
    $names = array(
      'active' => pht('Active Repositories'),
      'all' => pht('All Repositories'),
    );

    return $names;
  }

  public function buildSavedQueryFromBuiltin($query_key) {

    $query = $this->newSavedQuery();
    $query->setQueryKey($query_key);

    switch ($query_key) {
      case 'active':
        return $query->setParameter('status', 'open');
      case 'all':
        return $query;
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

  private function getStatusOptions() {
    return array(
      '' => pht('Active and Inactive Repositories'),
      'open' => pht('Active Repositories'),
      'closed' => pht('Inactive Repositories'),
    );
  }

  private function getStatusValues() {
    return array(
      '' => PhabricatorRepositoryQuery::STATUS_ALL,
      'open' => PhabricatorRepositoryQuery::STATUS_OPEN,
      'closed' => PhabricatorRepositoryQuery::STATUS_CLOSED,
    );
  }

}
