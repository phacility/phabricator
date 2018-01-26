<?php

final class PhabricatorRepositoryPushLogSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Push Logs');
  }

  public function getApplicationClassName() {
    return 'PhabricatorDiffusionApplication';
  }

  public function newQuery() {
    return new PhabricatorRepositoryPushLogQuery();
  }

  protected function buildQueryFromParameters(array $map) {
    $query = $this->newQuery();

    if ($map['repositoryPHIDs']) {
      $query->withRepositoryPHIDs($map['repositoryPHIDs']);
    }

    if ($map['pusherPHIDs']) {
      $query->withPusherPHIDs($map['pusherPHIDs']);
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
        ->setKey('pusherPHIDs')
        ->setAliases(array('pusher', 'pushers', 'pusherPHID'))
        ->setLabel(pht('Pushers'))
        ->setDescription(
          pht('Search for pull logs by specific users.')),
    );
  }

  protected function getURI($path) {
    return '/diffusion/pushlog/'.$path;
  }

  protected function getBuiltinQueryNames() {
    return array(
      'all' => pht('All Push Logs'),
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

    $table = id(new DiffusionPushLogListView())
      ->setViewer($this->requireViewer())
      ->setLogs($logs);

    return id(new PhabricatorApplicationSearchResultView())
      ->setTable($table);
  }

}
