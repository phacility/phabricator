<?php

final class PhabricatorRepositoryPullEventQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $repositoryPHIDs;
  private $pullerPHIDs;
  private $epochMin;
  private $epochMax;

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

  public function withPullerPHIDs(array $puller_phids) {
    $this->pullerPHIDs = $puller_phids;
    return $this;
  }

  public function withEpochBetween($min, $max) {
    $this->epochMin = $min;
    $this->epochMax = $max;
    return $this;
  }

  public function newResultObject() {
    return new PhabricatorRepositoryPullEvent();
  }

  protected function willFilterPage(array $events) {
    // If a pull targets an invalid repository or fails before authenticating,
    // it may not have an associated repository.

    $repository_phids = mpull($events, 'getRepositoryPHID');
    $repository_phids = array_filter($repository_phids);

    if ($repository_phids) {
      $repositories = id(new PhabricatorRepositoryQuery())
        ->setViewer($this->getViewer())
        ->withPHIDs($repository_phids)
        ->execute();
      $repositories = mpull($repositories, null, 'getPHID');
    } else {
      $repositories = array();
    }

    foreach ($events as $key => $event) {
      $phid = $event->getRepositoryPHID();
      if (!$phid) {
        $event->attachRepository(null);
        continue;
      }

      if (empty($repositories[$phid])) {
        unset($events[$key]);
        $this->didRejectResult($event);
        continue;
      }

      $event->attachRepository($repositories[$phid]);
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

    if ($this->pullerPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'pullerPHID in (%Ls)',
        $this->pullerPHIDs);
    }

    if ($this->epochMin !== null) {
      $where[] = qsprintf(
        $conn,
        'epoch >= %d',
        $this->epochMin);
    }

    if ($this->epochMax !== null) {
      $where[] = qsprintf(
        $conn,
        'epoch <= %d',
        $this->epochMax);
    }

    return $where;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorDiffusionApplication';
  }

}
