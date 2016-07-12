<?php

final class PhabricatorRepositoryPushEventQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $repositoryPHIDs;
  private $pusherPHIDs;
  private $needLogs;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withRepositoryPHIDs(array $repository_phids) {
    $this->repositoryPHIDs = $repository_phids;
    return $this;
  }

  public function withPusherPHIDs(array $pusher_phids) {
    $this->pusherPHIDs = $pusher_phids;
    return $this;
  }

  public function needLogs($need_logs) {
    $this->needLogs = $need_logs;
    return $this;
  }

  protected function loadPage() {
    $table = new PhabricatorRepositoryPushEvent();
    $conn_r = $table->establishConnection('r');

    $data = queryfx_all(
      $conn_r,
      'SELECT * FROM %T %Q %Q %Q',
      $table->getTableName(),
      $this->buildWhereClause($conn_r),
      $this->buildOrderClause($conn_r),
      $this->buildLimitClause($conn_r));

    return $table->loadAllFromArray($data);
  }

  protected function willFilterPage(array $events) {
    $repository_phids = mpull($events, 'getRepositoryPHID');
    $repositories = id(new PhabricatorRepositoryQuery())
      ->setViewer($this->getViewer())
      ->withPHIDs($repository_phids)
      ->execute();
    $repositories = mpull($repositories, null, 'getPHID');

    foreach ($events as $key => $event) {
      $phid = $event->getRepositoryPHID();
      if (empty($repositories[$phid])) {
        unset($events[$key]);
        continue;
      }
      $event->attachRepository($repositories[$phid]);
    }

    return $events;
  }

  protected function didFilterPage(array $events) {
    $phids = mpull($events, 'getPHID');

    if ($this->needLogs) {
      $logs = id(new PhabricatorRepositoryPushLogQuery())
        ->setParentQuery($this)
        ->setViewer($this->getViewer())
        ->withPushEventPHIDs($phids)
        ->execute();
      $logs = mgroup($logs, 'getPushEventPHID');
      foreach ($events as $key => $event) {
        $event_logs = idx($logs, $event->getPHID(), array());
        $event->attachLogs($event_logs);
      }
    }

    return $events;
  }

  protected function buildWhereClause(AphrontDatabaseConnection $conn_r) {
    $where = array();

    if ($this->ids) {
      $where[] = qsprintf(
        $conn_r,
        'id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids) {
      $where[] = qsprintf(
        $conn_r,
        'phid IN (%Ls)',
        $this->phids);
    }

    if ($this->repositoryPHIDs) {
      $where[] = qsprintf(
        $conn_r,
        'repositoryPHID IN (%Ls)',
        $this->repositoryPHIDs);
    }

    if ($this->pusherPHIDs) {
      $where[] = qsprintf(
        $conn_r,
        'pusherPHID in (%Ls)',
        $this->pusherPHIDs);
    }

    $where[] = $this->buildPagingClause($conn_r);

    return $this->formatWhereClause($where);
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorDiffusionApplication';
  }

}
