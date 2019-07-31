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

  public function newQuery() {
    $query = new PhabricatorPeopleLogQuery();

    // NOTE: If the viewer isn't an administrator, always restrict the query to
    // related records. This echoes the policy logic of these logs. This is
    // mostly a performance optimization, to prevent us from having to pull
    // large numbers of logs that the user will not be able to see and filter
    // them in-process.
    $viewer = $this->requireViewer();
    if (!$viewer->getIsAdmin()) {
      $query->withRelatedPHIDs(array($viewer->getPHID()));
    }

    return $query;
  }

  protected function buildQueryFromParameters(array $map) {
    $query = $this->newQuery();

    if ($map['userPHIDs']) {
      $query->withUserPHIDs($map['userPHIDs']);
    }

    if ($map['actorPHIDs']) {
      $query->withActorPHIDs($map['actorPHIDs']);
    }

    if ($map['actions']) {
      $query->withActions($map['actions']);
    }

    if (strlen($map['ip'])) {
      $query->withRemoteAddressPrefix($map['ip']);
    }

    if ($map['sessions']) {
      $query->withSessionKeys($map['sessions']);
    }

    if ($map['createdStart'] || $map['createdEnd']) {
      $query->withDateCreatedBetween(
        $map['createdStart'],
        $map['createdEnd']);
    }

    return $query;
  }

  protected function buildCustomSearchFields() {
    $types = PhabricatorUserLogType::getAllLogTypes();
    $types = mpull($types, 'getLogTypeName', 'getLogTypeKey');

    return array(
      id(new PhabricatorUsersSearchField())
        ->setKey('userPHIDs')
        ->setAliases(array('users', 'user', 'userPHID'))
        ->setLabel(pht('Users'))
        ->setDescription(pht('Search for activity affecting specific users.')),
      id(new PhabricatorUsersSearchField())
        ->setKey('actorPHIDs')
        ->setAliases(array('actors', 'actor', 'actorPHID'))
        ->setLabel(pht('Actors'))
        ->setDescription(pht('Search for activity by specific users.')),
      id(new PhabricatorSearchDatasourceField())
        ->setKey('actions')
        ->setLabel(pht('Actions'))
        ->setDescription(pht('Search for particular types of activity.'))
        ->setDatasource(new PhabricatorUserLogTypeDatasource()),
      id(new PhabricatorSearchTextField())
        ->setKey('ip')
        ->setLabel(pht('Filter IP'))
        ->setDescription(pht('Search for actions by remote address.')),
      id(new PhabricatorSearchStringListField())
        ->setKey('sessions')
        ->setLabel(pht('Sessions'))
        ->setDescription(pht('Search for activity in particular sessions.')),
      id(new PhabricatorSearchDateField())
        ->setLabel(pht('Created After'))
        ->setKey('createdStart'),
      id(new PhabricatorSearchDateField())
        ->setLabel(pht('Created Before'))
        ->setKey('createdEnd'),
    );
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

  protected function renderResultList(
    array $logs,
    PhabricatorSavedQuery $query,
    array $handles) {
    assert_instances_of($logs, 'PhabricatorUserLog');

    $viewer = $this->requireViewer();

    $table = id(new PhabricatorUserLogView())
      ->setUser($viewer)
      ->setLogs($logs);

    if ($viewer->getIsAdmin()) {
      $table->setSearchBaseURI($this->getApplicationURI('logs/'));
    }

    return id(new PhabricatorApplicationSearchResultView())
      ->setTable($table);
  }

  protected function newExportFields() {
    $viewer = $this->requireViewer();

    $fields = array(
      $fields[] = id(new PhabricatorPHIDExportField())
        ->setKey('actorPHID')
        ->setLabel(pht('Actor PHID')),
      $fields[] = id(new PhabricatorStringExportField())
        ->setKey('actor')
        ->setLabel(pht('Actor')),
      $fields[] = id(new PhabricatorPHIDExportField())
        ->setKey('userPHID')
        ->setLabel(pht('User PHID')),
      $fields[] = id(new PhabricatorStringExportField())
        ->setKey('user')
        ->setLabel(pht('User')),
      $fields[] = id(new PhabricatorStringExportField())
        ->setKey('action')
        ->setLabel(pht('Action')),
      $fields[] = id(new PhabricatorStringExportField())
        ->setKey('actionName')
        ->setLabel(pht('Action Name')),
      $fields[] = id(new PhabricatorStringExportField())
        ->setKey('session')
        ->setLabel(pht('Session')),
      $fields[] = id(new PhabricatorStringExportField())
        ->setKey('old')
        ->setLabel(pht('Old Value')),
      $fields[] = id(new PhabricatorStringExportField())
        ->setKey('new')
        ->setLabel(pht('New Value')),
    );

    if ($viewer->getIsAdmin()) {
      $fields[] = id(new PhabricatorStringExportField())
        ->setKey('remoteAddress')
        ->setLabel(pht('Remote Address'));
    }

    return $fields;
  }

  protected function newExportData(array $logs) {
    $viewer = $this->requireViewer();


    $phids = array();
    foreach ($logs as $log) {
      $phids[] = $log->getUserPHID();
      $phids[] = $log->getActorPHID();
    }
    $handles = $viewer->loadHandles($phids);

    $types = PhabricatorUserLogType::getAllLogTypes();
    $types = mpull($types, 'getLogTypeName', 'getLogTypeKey');

    $export = array();
    foreach ($logs as $log) {

      $user_phid = $log->getUserPHID();
      if ($user_phid) {
        $user_name = $handles[$user_phid]->getName();
      } else {
        $user_name = null;
      }

      $actor_phid = $log->getActorPHID();
      if ($actor_phid) {
        $actor_name = $handles[$actor_phid]->getName();
      } else {
        $actor_name = null;
      }

      $action = $log->getAction();
      $action_name = idx($types, $action, pht('Unknown ("%s")', $action));

      $map = array(
        'actorPHID' => $actor_phid,
        'actor' => $actor_name,
        'userPHID' => $user_phid,
        'user' => $user_name,
        'action' => $action,
        'actionName' => $action_name,
        'session' => substr($log->getSession(), 0, 6),
        'old' => $log->getOldValue(),
        'new' => $log->getNewValue(),
      );

      if ($viewer->getIsAdmin()) {
        $map['remoteAddress'] = $log->getRemoteAddr();
      }

      $export[] = $map;
    }

    return $export;
  }

}
