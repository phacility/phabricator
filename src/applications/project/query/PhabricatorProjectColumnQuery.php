<?php

final class PhabricatorProjectColumnQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $projectPHIDs;
  private $statuses;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withProjectPHIDs(array $project_phids) {
    $this->projectPHIDs = $project_phids;
    return $this;
  }

  public function withStatuses(array $status) {
    $this->statuses = $status;
    return $this;
  }

  public function newResultObject() {
    return new PhabricatorProjectColumn();
  }

  protected function loadPage() {
    return $this->loadStandardPage($this->newResultObject());
  }

  protected function willFilterPage(array $page) {
    $projects = array();

    $project_phids = array_filter(mpull($page, 'getProjectPHID'));
    if ($project_phids) {
      $projects = id(new PhabricatorProjectQuery())
        ->setParentQuery($this)
        ->setViewer($this->getViewer())
        ->withPHIDs($project_phids)
        ->execute();
      $projects = mpull($projects, null, 'getPHID');
    }

    foreach ($page as $key => $column) {
      $phid = $column->getProjectPHID();
      $project = idx($projects, $phid);
      if (!$project) {
        $this->didRejectResult($page[$key]);
        unset($page[$key]);
        continue;
      }
      $column->attachProject($project);
    }

    return $page;
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

    if ($this->projectPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'projectPHID IN (%Ls)',
        $this->projectPHIDs);
    }

    if ($this->statuses !== null) {
      $where[] = qsprintf(
        $conn,
        'status IN (%Ld)',
        $this->statuses);
    }

    return $where;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorProjectApplication';
  }

}
