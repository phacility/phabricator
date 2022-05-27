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
  private $epochMin;
  private $epochMax;
  private $blockingHeraldRulePHIDs;

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

  public function withEpochBetween($min, $max) {
    $this->epochMin = $min;
    $this->epochMax = $max;
    return $this;
  }

  public function withBlockingHeraldRulePHIDs(array $phids) {
    $this->blockingHeraldRulePHIDs = $phids;
    return $this;
  }

  public function newResultObject() {
    return new PhabricatorRepositoryPushLog();
  }

  protected function willFilterPage(array $logs) {
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

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'log.id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn,
        'log.phid IN (%Ls)',
        $this->phids);
    }

    if ($this->repositoryPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'log.repositoryPHID IN (%Ls)',
        $this->repositoryPHIDs);
    }

    if ($this->pusherPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'log.pusherPHID in (%Ls)',
        $this->pusherPHIDs);
    }

    if ($this->pushEventPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'log.pushEventPHID in (%Ls)',
        $this->pushEventPHIDs);
    }

    if ($this->refTypes !== null) {
      $where[] = qsprintf(
        $conn,
        'log.refType IN (%Ls)',
        $this->refTypes);
    }

    if ($this->newRefs !== null) {
      $where[] = qsprintf(
        $conn,
        'log.refNew IN (%Ls)',
        $this->newRefs);
    }

    if ($this->epochMin !== null) {
      $where[] = qsprintf(
        $conn,
        'log.epoch >= %d',
        $this->epochMin);
    }

    if ($this->epochMax !== null) {
      $where[] = qsprintf(
        $conn,
        'log.epoch <= %d',
        $this->epochMax);
    }

    if ($this->blockingHeraldRulePHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        '(event.rejectCode = %d AND event.rejectDetails IN (%Ls))',
        PhabricatorRepositoryPushLog::REJECT_HERALD,
        $this->blockingHeraldRulePHIDs);
    }

    return $where;
  }

  protected function buildJoinClauseParts(AphrontDatabaseConnection $conn) {
    $joins = parent::buildJoinClauseParts($conn);

    if ($this->shouldJoinPushEventTable()) {
      $joins[] = qsprintf(
        $conn,
        'JOIN %T event ON event.phid = log.pushEventPHID',
        id(new PhabricatorRepositoryPushEvent())->getTableName());
    }

    return $joins;
  }

  private function shouldJoinPushEventTable() {
    if ($this->blockingHeraldRulePHIDs !== null) {
      return true;
    }

    return false;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorDiffusionApplication';
  }

  protected function getPrimaryTableAlias() {
    return 'log';
  }


}
