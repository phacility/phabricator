<?php

final class HarbormasterBuildTargetSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Harbormaster Build Targets');
  }

  public function getApplicationClassName() {
    return 'PhabricatorHarbormasterApplication';
  }

  public function newQuery() {
    return new HarbormasterBuildTargetQuery();
  }

  protected function buildCustomSearchFields() {
    return array(
      id(new PhabricatorSearchDatasourceField())
        ->setLabel(pht('Builds'))
        ->setKey('buildPHIDs')
        ->setAliases(array('build', 'builds', 'buildPHID'))
        ->setDescription(
          pht('Search for targets of a given build.'))
        ->setDatasource(new HarbormasterBuildPlanDatasource()),
    );
  }

  protected function buildQueryFromParameters(array $map) {
    $query = $this->newQuery();

    if ($map['buildPHIDs']) {
      $query->withBuildPHIDs($map['buildPHIDs']);
    }

    return $query;
  }

  protected function getURI($path) {
    return '/harbormaster/target/'.$path;
  }

  protected function getBuiltinQueryNames() {
    return array(
      'all' => pht('All Targets'),
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
    assert_instances_of($builds, 'HarbormasterBuildTarget');

    // Currently, this only supports the "harbormaster.target.search"
    // API method.
    throw new PhutilMethodNotImplementedException();
  }

}
