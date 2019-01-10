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

    if ($map['createdStart'] || $map['createdEnd']) {
      $query->withEpochBetween(
        $map['createdStart'],
        $map['createdEnd']);
    }

    if ($map['blockingHeraldRulePHIDs']) {
      $query->withBlockingHeraldRulePHIDs($map['blockingHeraldRulePHIDs']);
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
          pht('Search for push logs for specific repositories.')),
      id(new PhabricatorUsersSearchField())
        ->setKey('pusherPHIDs')
        ->setAliases(array('pusher', 'pushers', 'pusherPHID'))
        ->setLabel(pht('Pushers'))
        ->setDescription(
          pht('Search for push logs by specific users.')),
      id(new PhabricatorSearchDatasourceField())
        ->setDatasource(new HeraldRuleDatasource())
        ->setKey('blockingHeraldRulePHIDs')
        ->setLabel(pht('Blocked By'))
        ->setDescription(
          pht('Search for pushes blocked by particular Herald rules.')),
      id(new PhabricatorSearchDateField())
        ->setLabel(pht('Created After'))
        ->setKey('createdStart'),
      id(new PhabricatorSearchDateField())
        ->setLabel(pht('Created Before'))
        ->setKey('createdEnd'),
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

  protected function newExportFields() {
    $viewer = $this->requireViewer();

    $fields = array(
      id(new PhabricatorIDExportField())
        ->setKey('pushID')
        ->setLabel(pht('Push ID')),
      id(new PhabricatorStringExportField())
        ->setKey('unique')
        ->setLabel(pht('Unique')),
      id(new PhabricatorStringExportField())
        ->setKey('protocol')
        ->setLabel(pht('Protocol')),
      id(new PhabricatorPHIDExportField())
        ->setKey('repositoryPHID')
        ->setLabel(pht('Repository PHID')),
      id(new PhabricatorStringExportField())
        ->setKey('repository')
        ->setLabel(pht('Repository')),
      id(new PhabricatorPHIDExportField())
        ->setKey('pusherPHID')
        ->setLabel(pht('Pusher PHID')),
      id(new PhabricatorStringExportField())
        ->setKey('pusher')
        ->setLabel(pht('Pusher')),
      id(new PhabricatorPHIDExportField())
        ->setKey('devicePHID')
        ->setLabel(pht('Device PHID')),
      id(new PhabricatorStringExportField())
        ->setKey('device')
        ->setLabel(pht('Device')),
      id(new PhabricatorStringExportField())
        ->setKey('type')
        ->setLabel(pht('Ref Type')),
      id(new PhabricatorStringExportField())
        ->setKey('name')
        ->setLabel(pht('Ref Name')),
      id(new PhabricatorStringExportField())
        ->setKey('old')
        ->setLabel(pht('Ref Old')),
      id(new PhabricatorStringExportField())
        ->setKey('new')
        ->setLabel(pht('Ref New')),
      id(new PhabricatorIntExportField())
        ->setKey('flags')
        ->setLabel(pht('Flags')),
      id(new PhabricatorStringListExportField())
        ->setKey('flagNames')
        ->setLabel(pht('Flag Names')),
      id(new PhabricatorIntExportField())
        ->setKey('result')
        ->setLabel(pht('Result')),
      id(new PhabricatorStringExportField())
        ->setKey('resultName')
        ->setLabel(pht('Result Name')),
      id(new PhabricatorStringExportField())
        ->setKey('resultDetails')
        ->setLabel(pht('Result Details')),
      id(new PhabricatorIntExportField())
        ->setKey('hostWait')
        ->setLabel(pht('Host Wait (us)')),
      id(new PhabricatorIntExportField())
        ->setKey('writeWait')
        ->setLabel(pht('Write Wait (us)')),
      id(new PhabricatorIntExportField())
        ->setKey('readWait')
        ->setLabel(pht('Read Wait (us)')),
      id(new PhabricatorIntExportField())
        ->setKey('hookWait')
        ->setLabel(pht('Hook Wait (us)')),
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
      $phids[] = $log->getPusherPHID();
      $phids[] = $log->getDevicePHID();
      $phids[] = $log->getPushEvent()->getRepositoryPHID();
    }
    $handles = $viewer->loadHandles($phids);

    $flag_map = PhabricatorRepositoryPushLog::getFlagDisplayNames();
    $reject_map = PhabricatorRepositoryPushLog::getRejectCodeDisplayNames();

    $export = array();
    foreach ($logs as $log) {
      $event = $log->getPushEvent();

      $repository_phid = $event->getRepositoryPHID();
      if ($repository_phid) {
        $repository_name = $handles[$repository_phid]->getName();
      } else {
        $repository_name = null;
      }

      $pusher_phid = $log->getPusherPHID();
      if ($pusher_phid) {
        $pusher_name = $handles[$pusher_phid]->getName();
      } else {
        $pusher_name = null;
      }

      $device_phid = $log->getDevicePHID();
      if ($device_phid) {
        $device_name = $handles[$device_phid]->getName();
      } else {
        $device_name = null;
      }

      $flags = $log->getChangeFlags();
      $flag_names = array();
      foreach ($flag_map as $flag_key => $flag_name) {
        if (($flags & $flag_key) === $flag_key) {
          $flag_names[] = $flag_name;
        }
      }

      $result = $event->getRejectCode();
      $result_name = idx($reject_map, $result, pht('Unknown ("%s")', $result));

      $map = array(
        'pushID' => $event->getID(),
        'unique' => $event->getRequestIdentifier(),
        'protocol' => $event->getRemoteProtocol(),
        'repositoryPHID' => $repository_phid,
        'repository' => $repository_name,
        'pusherPHID' => $pusher_phid,
        'pusher' => $pusher_name,
        'devicePHID' => $device_phid,
        'device' => $device_name,
        'type' => $log->getRefType(),
        'name' => $log->getRefName(),
        'old' => $log->getRefOld(),
        'new' => $log->getRefNew(),
        'flags' => $flags,
        'flagNames' => $flag_names,
        'result' => $result,
        'resultName' => $result_name,
        'resultDetails' => $event->getRejectDetails(),
        'hostWait' => $event->getHostWait(),
        'writeWait' => $event->getWriteWait(),
        'readWait' => $event->getReadWait(),
        'hookWait' => $event->getHookWait(),
      );

      if ($viewer->getIsAdmin()) {
        $map['remoteAddress'] = $event->getRemoteAddress();
      }

      $export[] = $map;
    }

    return $export;
  }

}
