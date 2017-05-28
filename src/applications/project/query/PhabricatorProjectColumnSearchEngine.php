<?php

final class PhabricatorProjectColumnSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Workboard Columns');
  }

  public function getApplicationClassName() {
    return 'PhabricatorProjectApplication';
  }

  public function canUseInPanelContext() {
    return false;
  }

  public function newQuery() {
    return new PhabricatorProjectColumnQuery();
  }

  protected function buildCustomSearchFields() {
    return array(
      id(new PhabricatorPHIDsSearchField())
        ->setLabel(pht('Projects'))
        ->setKey('projectPHIDs')
        ->setConduitKey('projects')
        ->setAliases(array('project', 'projects', 'projectPHID')),
    );
  }

  protected function buildQueryFromParameters(array $map) {
    $query = $this->newQuery();

    if ($map['projectPHIDs']) {
      $query->withProjectPHIDs($map['projectPHIDs']);
    }

    return $query;
  }

  protected function getURI($path) {
    // NOTE: There's no way to query columns in the web UI, at least for
    // the moment.
    return null;
  }

  protected function getBuiltinQueryNames() {
    $names = array();

    $names['all'] = pht('All');

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

  protected function renderResultList(
    array $projects,
    PhabricatorSavedQuery $query,
    array $handles) {
    assert_instances_of($projects, 'PhabricatorProjectColumn');
    $viewer = $this->requireViewer();

    return null;
  }

}
