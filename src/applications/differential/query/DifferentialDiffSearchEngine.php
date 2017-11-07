<?php

final class DifferentialDiffSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Differential Diffs');
  }

  public function getApplicationClassName() {
    return 'PhabricatorDifferentialApplication';
  }

  public function newQuery() {
    return new DifferentialDiffQuery();
  }

  protected function buildQueryFromParameters(array $map) {
    $query = $this->newQuery();

    if ($map['revisionPHIDs']) {
      $query->withRevisionPHIDs($map['revisionPHIDs']);
    }

    return $query;
  }

  protected function buildCustomSearchFields() {
    return array(
      id(new PhabricatorPHIDsSearchField())
        ->setLabel(pht('Revisions'))
        ->setKey('revisionPHIDs')
        ->setAliases(array('revision', 'revisions', 'revisionPHID'))
        ->setDescription(
          pht('Find diffs attached to a particular revision.')),
    );
  }

  protected function getURI($path) {
    return '/differential/diff/'.$path;
  }

  protected function getBuiltinQueryNames() {
    $names = array();

    $names['all'] = pht('All Diffs');

    return $names;
  }

  public function buildSavedQueryFromBuiltin($query_key) {
    $query = $this->newSavedQuery();
    $query->setQueryKey($query_key);

    $viewer = $this->requireViewer();

    switch ($query_key) {
      case 'all':
        return $query;
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

  protected function renderResultList(
    array $revisions,
    PhabricatorSavedQuery $query,
    array $handles) {
    assert_instances_of($revisions, 'DifferentialDiff');

    $viewer = $this->requireViewer();

    // NOTE: This is only exposed to Conduit, so we don't currently render
    // results.

    return id(new PhabricatorApplicationSearchResultView());
  }

}
