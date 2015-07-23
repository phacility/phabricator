<?php

final class PhabricatorRepositoryPushLogSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Push Logs');
  }

  public function getApplicationClassName() {
    return 'PhabricatorDiffusionApplication';
  }

  public function buildSavedQueryFromRequest(AphrontRequest $request) {
    $saved = new PhabricatorSavedQuery();

    $saved->setParameter(
      'repositoryPHIDs',
      $this->readPHIDsFromRequest(
        $request,
        'repositories',
        array(
          PhabricatorRepositoryRepositoryPHIDType::TYPECONST,
        )));

    $saved->setParameter(
      'pusherPHIDs',
      $this->readUsersFromRequest(
        $request,
        'pushers'));

    return $saved;
  }

  public function buildQueryFromSavedQuery(PhabricatorSavedQuery $saved) {
    $query = id(new PhabricatorRepositoryPushLogQuery());

    $repository_phids = $saved->getParameter('repositoryPHIDs');
    if ($repository_phids) {
      $query->withRepositoryPHIDs($repository_phids);
    }

    $pusher_phids = $saved->getParameter('pusherPHIDs');
    if ($pusher_phids) {
      $query->withPusherPHIDs($pusher_phids);
    }

    return $query;
  }

  public function buildSearchForm(
    AphrontFormView $form,
    PhabricatorSavedQuery $saved_query) {

    $repository_phids = $saved_query->getParameter('repositoryPHIDs', array());
    $pusher_phids = $saved_query->getParameter('pusherPHIDs', array());

    $form
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setDatasource(new DiffusionRepositoryDatasource())
          ->setName('repositories')
          ->setLabel(pht('Repositories'))
          ->setValue($repository_phids))
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setDatasource(new PhabricatorPeopleDatasource())
          ->setName('pushers')
          ->setLabel(pht('Pushers'))
          ->setValue($pusher_phids));
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

  protected function getRequiredHandlePHIDsForResultList(
    array $logs,
    PhabricatorSavedQuery $query) {
    return mpull($logs, 'getPusherPHID');
  }

  protected function renderResultList(
    array $logs,
    PhabricatorSavedQuery $query,
    array $handles) {

    $table = id(new DiffusionPushLogListView())
      ->setUser($this->requireViewer())
      ->setHandles($handles)
      ->setLogs($logs);

    return id(new PhabricatorApplicationSearchResultView())
      ->setTable($table);
  }

}
