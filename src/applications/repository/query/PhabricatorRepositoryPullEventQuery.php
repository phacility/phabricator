<?php

final class PhabricatorRepositoryPullEventQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $repositoryPHIDs;
  private $pullerPHIDs;

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

  public function newResultObject() {
    return new PhabricatorRepositoryPullEvent();
  }

  protected function loadPage() {
    return $this->loadStandardPage($this->newResultObject());
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

    return $where;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorDiffusionApplication';
  }

}
