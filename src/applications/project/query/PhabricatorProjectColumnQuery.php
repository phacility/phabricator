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

  protected function loadPage() {
    $table = new PhabricatorProjectColumn();
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
        unset($page[$key]);
        continue;
      }
      $column->attachProject($project);
    }

    return $page;
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

    if ($this->projectPHIDs) {
      $where[] = qsprintf(
        $conn_r,
        'projectPHID IN (%Ls)',
        $this->projectPHIDs);
    }

    if ($this->statuses !== null) {
      $where[] = qsprintf(
        $conn_r,
        'status IN (%Ld)',
        $this->statuses);
    }

    $where[] = $this->buildPagingClause($conn_r);

    return $this->formatWhereClause($where);
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorProjectApplication';
  }

}
