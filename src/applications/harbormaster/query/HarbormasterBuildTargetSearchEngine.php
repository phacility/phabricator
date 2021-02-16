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
      id(new PhabricatorSearchDateField())
        ->setLabel(pht('Created After'))
        ->setKey('createdStart')
        ->setDescription(
          pht('Search for targets created on or after a particular date.')),
      id(new PhabricatorSearchDateField())
        ->setLabel(pht('Created Before'))
        ->setKey('createdEnd')
        ->setDescription(
          pht('Search for targets created on or before a particular date.')),
      id(new PhabricatorSearchDateField())
        ->setLabel(pht('Started After'))
        ->setKey('startedStart')
        ->setDescription(
          pht('Search for targets started on or after a particular date.')),
      id(new PhabricatorSearchDateField())
        ->setLabel(pht('Started Before'))
        ->setKey('startedEnd')
        ->setDescription(
          pht('Search for targets started on or before a particular date.')),
      id(new PhabricatorSearchDateField())
        ->setLabel(pht('Completed After'))
        ->setKey('completedStart')
        ->setDescription(
          pht('Search for targets completed on or after a particular date.')),
      id(new PhabricatorSearchDateField())
        ->setLabel(pht('Completed Before'))
        ->setKey('completedEnd')
        ->setDescription(
          pht('Search for targets completed on or before a particular date.')),
      id(new PhabricatorSearchStringListField())
        ->setLabel(pht('Statuses'))
        ->setKey('statuses')
        ->setAliases(array('status'))
        ->setDescription(
          pht('Search for targets with given statuses.')),
    );
  }

  protected function buildQueryFromParameters(array $map) {
    $query = $this->newQuery();

    if ($map['buildPHIDs']) {
      $query->withBuildPHIDs($map['buildPHIDs']);
    }

    if ($map['createdStart'] !== null || $map['createdEnd'] !== null) {
      $query->withDateCreatedBetween(
        $map['createdStart'],
        $map['createdEnd']);
    }

    if ($map['startedStart'] !== null || $map['startedEnd'] !== null) {
      $query->withDateStartedBetween(
        $map['startedStart'],
        $map['startedEnd']);
    }

    if ($map['completedStart'] !== null || $map['completedEnd'] !== null) {
      $query->withDateCompletedBetween(
        $map['completedStart'],
        $map['completedEnd']);
    }

    if ($map['statuses']) {
      $query->withTargetStatuses($map['statuses']);
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
