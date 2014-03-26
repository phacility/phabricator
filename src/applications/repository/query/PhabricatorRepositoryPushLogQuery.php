<?php

final class PhabricatorRepositoryPushLogQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $repositoryPHIDs;
  private $pusherPHIDs;
  private $refTypes;
  private $newRefs;
  private $pushEventPHIDs;

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

  public function withRefTypes(array $ref_types) {
    $this->refTypes = $ref_types;
    return $this;
  }

  public function withNewRefs(array $new_refs) {
    $this->newRefs = $new_refs;
    return $this;
  }

  public function withPushEventPHIDs(array $phids) {
    $this->pushEventPHIDs = $phids;
    return $this;
  }

  protected function loadPage() {
    $table = new PhabricatorRepositoryPushLog();
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

  public function willFilterPage(array $logs) {
    $event_phids = mpull($logs, 'getPushEventPHID');
    $events = id(new PhabricatorObjectQuery())
      ->setParentQuery($this)
      ->setViewer($this->getViewer())
      ->withPHIDs($event_phids)
      ->execute();
    $events = mpull($events, null, 'getPHID');

    foreach ($logs as $key => $log) {
      $event = idx($events, $log->getPushEventPHID());
      if (!$event) {
        unset($logs[$key]);
        continue;
      }
      $log->attachPushEvent($event);
    }

    return $logs;
  }


  private function buildWhereClause(AphrontDatabaseConnection $conn_r) {
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

    if ($this->pushEventPHIDs) {
      $where[] = qsprintf(
        $conn_r,
        'pushEventPHID in (%Ls)',
        $this->pushEventPHIDs);
    }

    if ($this->refTypes) {
      $where[] = qsprintf(
        $conn_r,
        'refType IN (%Ls)',
        $this->refTypes);
    }

    if ($this->newRefs) {
      $where[] = qsprintf(
        $conn_r,
        'refNew IN (%Ls)',
        $this->newRefs);
    }

    $where[] = $this->buildPagingClause($conn_r);

    return $this->formatWhereClause($where);
  }


  public function getQueryApplicationClass() {
    return 'PhabricatorApplicationDiffusion';
  }

}
