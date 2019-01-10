<?php

final class DiffusionSyncLogSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Sync Logs');
  }

  public function getApplicationClassName() {
    return 'PhabricatorDiffusionApplication';
  }

  public function newQuery() {
    return new PhabricatorRepositorySyncEventQuery();
  }

  protected function buildQueryFromParameters(array $map) {
    $query = $this->newQuery();

    if ($map['repositoryPHIDs']) {
      $query->withRepositoryPHIDs($map['repositoryPHIDs']);
    }

    if ($map['createdStart'] || $map['createdEnd']) {
      $query->withEpochBetween(
        $map['createdStart'],
        $map['createdEnd']);
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
          pht('Search for sync logs for specific repositories.')),
      id(new PhabricatorSearchDateField())
        ->setLabel(pht('Created After'))
        ->setKey('createdStart'),
      id(new PhabricatorSearchDateField())
        ->setLabel(pht('Created Before'))
        ->setKey('createdEnd'),
    );
  }

  protected function newExportFields() {
    $viewer = $this->requireViewer();

    $fields = array(
      id(new PhabricatorPHIDExportField())
        ->setKey('repositoryPHID')
        ->setLabel(pht('Repository PHID')),
      id(new PhabricatorStringExportField())
        ->setKey('repository')
        ->setLabel(pht('Repository')),
      id(new PhabricatorPHIDExportField())
        ->setKey('devicePHID')
        ->setLabel(pht('Device PHID')),
      id(new PhabricatorPHIDExportField())
        ->setKey('fromDevicePHID')
        ->setLabel(pht('From Device PHID')),
      id(new PhabricatorIntExportField())
        ->setKey('deviceVersion')
        ->setLabel(pht('Device Version')),
      id(new PhabricatorIntExportField())
        ->setKey('fromDeviceVersion')
        ->setLabel(pht('From Device Version')),
      id(new PhabricatorStringExportField())
        ->setKey('result')
        ->setLabel(pht('Result')),
      id(new PhabricatorIntExportField())
        ->setKey('code')
        ->setLabel(pht('Code')),
      id(new PhabricatorEpochExportField())
        ->setKey('date')
        ->setLabel(pht('Date')),
      id(new PhabricatorIntExportField())
        ->setKey('syncWait')
        ->setLabel(pht('Sync Wait')),
    );

    return $fields;
  }

  protected function newExportData(array $events) {
    $viewer = $this->requireViewer();

    $export = array();
    foreach ($events as $event) {
      $repository = $event->getRepository();
      $repository_phid = $repository->getPHID();
      $repository_name = $repository->getDisplayName();

      $map = array(
        'repositoryPHID' => $repository_phid,
        'repository' => $repository_name,
        'devicePHID' => $event->getDevicePHID(),
        'fromDevicePHID' => $event->getFromDevicePHID(),
        'deviceVersion' => $event->getDeviceVersion(),
        'fromDeviceVersion' => $event->getFromDeviceVersion(),
        'result' => $event->getResultType(),
        'code' => $event->getResultCode(),
        'date' => $event->getEpoch(),
        'syncWait' => $event->getSyncWait(),
      );

      $export[] = $map;
    }

    return $export;
  }

  protected function getURI($path) {
    return '/diffusion/synclog/'.$path;
  }

  protected function getBuiltinQueryNames() {
    return array(
      'all' => pht('All Sync Logs'),
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

    $table = id(new DiffusionSyncLogListView())
      ->setViewer($this->requireViewer())
      ->setLogs($logs);

    return id(new PhabricatorApplicationSearchResultView())
      ->setTable($table);
  }

}
