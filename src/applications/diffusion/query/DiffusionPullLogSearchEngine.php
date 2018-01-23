<?php

final class DiffusionPullLogSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Pull Logs');
  }

  public function getApplicationClassName() {
    return 'PhabricatorDiffusionApplication';
  }

  public function newQuery() {
    return new PhabricatorRepositoryPullEventQuery();
  }

  protected function buildQueryFromParameters(array $map) {
    $query = $this->newQuery();

    if ($map['repositoryPHIDs']) {
      $query->withRepositoryPHIDs($map['repositoryPHIDs']);
    }

    if ($map['pullerPHIDs']) {
      $query->withPullerPHIDs($map['pullerPHIDs']);
    }

    return $query;
  }

  protected function buildCustomSearchFields() {
    return array(
      id(new PhabricatorSearchDatasourceField())
        ->setDatasource(new DiffusionRepositoryDatasource())
        ->setKey('repositoryPHIDs')
        ->setAliases(array('repository', 'repositories', 'repositoryPHID'))
        ->setLabel(pht('Repositories'))
        ->setDescription(
          pht('Search for pull logs for specific repositories.')),
      id(new PhabricatorUsersSearchField())
        ->setKey('pullerPHIDs')
        ->setAliases(array('puller', 'pullers', 'pullerPHID'))
        ->setLabel(pht('Pullers'))
        ->setDescription(
          pht('Search for pull logs by specific users.')),
    );
  }

  protected function getURI($path) {
    return '/diffusion/pulllog/'.$path;
  }

  protected function getBuiltinQueryNames() {
    return array(
      'all' => pht('All Pull Logs'),
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
    array $logs,
    PhabricatorSavedQuery $query,
    array $handles) {

    $table = id(new DiffusionPullLogListView())
      ->setViewer($this->requireViewer())
      ->setLogs($logs);

    return id(new PhabricatorApplicationSearchResultView())
      ->setTable($table);
  }

}
