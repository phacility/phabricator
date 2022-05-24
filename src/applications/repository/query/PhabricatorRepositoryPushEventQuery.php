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

  public function newResultObject() {
    return new PhabricatorRepositoryPushEvent();
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

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn,
        'phid IN (%Ls)',
        $this->phids);
    }

    if ($this->repositoryPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'repositoryPHID IN (%Ls)',
        $this->repositoryPHIDs);
    }

    if ($this->pusherPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'pusherPHID in (%Ls)',
        $this->pusherPHIDs);
    }

    return $where;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorDiffusionApplication';
  }

}
