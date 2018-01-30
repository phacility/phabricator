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

    return $query;
  }

  protected function buildCustomSearchFields() {
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
      id(new PhabricatorSearchCheckboxesField())
        ->setKey('actions')
        ->setLabel(pht('Actions'))
        ->setDescription(pht('Search for particular types of activity.'))
        ->setOptions(PhabricatorUserLog::getActionTypeMap()),
      id(new PhabricatorSearchTextField())
        ->setKey('ip')
        ->setLabel(pht('Filter IP'))
        ->setDescription(pht('Search for actions by remote address.')),
      id(new PhabricatorSearchStringListField())
        ->setKey('sessions')
        ->setLabel(pht('Sessions'))
        ->setDescription(pht('Search for activity in particular sessions.')),
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
}
