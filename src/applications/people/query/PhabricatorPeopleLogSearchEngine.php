<?php

final class PhabricatorPeopleLogSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Account Activity');
  }

  public function getApplicationClassName() {
    return 'PhabricatorPeopleApplication';
  }

  public function getPageSize(PhabricatorSavedQuery $saved) {
    return 500;
  }

  public function buildSavedQueryFromRequest(AphrontRequest $request) {
    $saved = new PhabricatorSavedQuery();

    $saved->setParameter(
      'userPHIDs',
      $this->readUsersFromRequest($request, 'users'));

    $saved->setParameter(
      'actorPHIDs',
      $this->readUsersFromRequest($request, 'actors'));

    $saved->setParameter(
      'actions',
      $this->readListFromRequest($request, 'actions'));

    $saved->setParameter(
      'ip',
      $request->getStr('ip'));

    $saved->setParameter(
      'sessions',
      $this->readListFromRequest($request, 'sessions'));

    return $saved;
  }

  public function buildQueryFromSavedQuery(PhabricatorSavedQuery $saved) {
    $query = id(new PhabricatorPeopleLogQuery());

    // NOTE: If the viewer isn't an administrator, always restrict the query to
    // related records. This echoes the policy logic of these logs. This is
    // mostly a performance optimization, to prevent us from having to pull
    // large numbers of logs that the user will not be able to see and filter
    // them in-process.
    $viewer = $this->requireViewer();
    if (!$viewer->getIsAdmin()) {
      $query->withRelatedPHIDs(array($viewer->getPHID()));
    }

    $actor_phids = $saved->getParameter('actorPHIDs', array());
    if ($actor_phids) {
      $query->withActorPHIDs($actor_phids);
    }

    $user_phids = $saved->getParameter('userPHIDs', array());
    if ($user_phids) {
      $query->withUserPHIDs($user_phids);
    }

    $actions = $saved->getParameter('actions', array());
    if ($actions) {
      $query->withActions($actions);
    }

    $remote_prefix = $saved->getParameter('ip');
    if (strlen($remote_prefix)) {
      $query->withRemoteAddressprefix($remote_prefix);
    }

    $sessions = $saved->getParameter('sessions', array());
    if ($sessions) {
      $query->withSessionKeys($sessions);
    }

    return $query;
  }

  public function buildSearchForm(
    AphrontFormView $form,
    PhabricatorSavedQuery $saved) {

    $actor_phids = $saved->getParameter('actorPHIDs', array());
    $user_phids = $saved->getParameter('userPHIDs', array());

    $actions = $saved->getParameter('actions', array());
    $remote_prefix = $saved->getParameter('ip');
    $sessions = $saved->getParameter('sessions', array());

    $actions = array_fuse($actions);
    $action_control = id(new AphrontFormCheckboxControl())
      ->setLabel(pht('Actions'));
    $action_types = PhabricatorUserLog::getActionTypeMap();
    foreach ($action_types as $type => $label) {
      $action_control->addCheckbox(
        'actions[]',
        $type,
        $label,
        isset($actions[$label]));
    }

    $form
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setDatasource(new PhabricatorPeopleDatasource())
          ->setName('actors')
          ->setLabel(pht('Actors'))
          ->setValue($actor_phids))
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setDatasource(new PhabricatorPeopleDatasource())
          ->setName('users')
          ->setLabel(pht('Users'))
          ->setValue($user_phids))
      ->appendChild($action_control)
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Filter IP'))
          ->setName('ip')
          ->setValue($remote_prefix))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Sessions'))
          ->setName('sessions')
          ->setValue(implode(', ', $sessions)));

  }

  protected function getURI($path) {
    return '/people/logs/'.$path;
  }

  protected function getBuiltinQueryNames() {
    $names = array(
      'all' => pht('All'),
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

  protected function getRequiredHandlePHIDsForResultList(
    array $logs,
    PhabricatorSavedQuery $query) {

    $phids = array();
    foreach ($logs as $log) {
      $phids[$log->getActorPHID()] = true;
      $phids[$log->getUserPHID()] = true;
    }

    return array_keys($phids);
  }

  protected function renderResultList(
    array $logs,
    PhabricatorSavedQuery $query,
    array $handles) {
    assert_instances_of($logs, 'PhabricatorUserLog');

    $viewer = $this->requireViewer();

    $table = id(new PhabricatorUserLogView())
      ->setUser($viewer)
      ->setLogs($logs)
      ->setHandles($handles);

    if ($viewer->getIsAdmin()) {
      $table->setSearchBaseURI($this->getApplicationURI('logs/'));
    }

    $result = new PhabricatorApplicationSearchResultView();
    $result->setTable($table);

    return $result;
  }
}
