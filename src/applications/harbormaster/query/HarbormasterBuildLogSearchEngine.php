<?php

final class HarbormasterBuildLogSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Harbormaster Build Logs');
  }

  public function getApplicationClassName() {
    return 'PhabricatorHarbormasterApplication';
  }

  public function newQuery() {
    return new HarbormasterBuildLogQuery();
  }

  protected function buildCustomSearchFields() {
    return array(
      id(new PhabricatorPHIDsSearchField())
        ->setLabel(pht('Build Targets'))
        ->setKey('buildTargetPHIDs')
        ->setAliases(
          array(
            'buildTargetPHID',
            'buildTargets',
            'buildTarget',
            'targetPHIDs',
            'targetPHID',
            'targets',
            'target',
          ))
        ->setDescription(
          pht('Search for logs that belong to a particular build target.')),
    );
  }

  protected function buildQueryFromParameters(array $map) {
    $query = $this->newQuery();

    if ($map['buildTargetPHIDs']) {
      $query->withBuildTargetPHIDs($map['buildTargetPHIDs']);
    }

    return $query;
  }

  protected function getURI($path) {
    return '/harbormaster/log/'.$path;
  }

  protected function getBuiltinQueryNames() {
    return array(
      'all' => pht('All Builds'),
    );
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
    array $builds,
    PhabricatorSavedQuery $query,
    array $handles) {

    // For now, this SearchEngine is only for driving the API.
    throw new PhutilMethodNotImplementedException();
  }

}
