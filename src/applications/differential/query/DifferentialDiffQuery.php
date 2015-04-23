<?php

final class DifferentialDiffQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $revisionIDs;
  private $needChangesets = false;
  private $needArcanistProjects = false;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withRevisionIDs(array $revision_ids) {
    $this->revisionIDs = $revision_ids;
    return $this;
  }

  public function needChangesets($bool) {
    $this->needChangesets = $bool;
    return $this;
  }

  public function needArcanistProjects($bool) {
    $this->needArcanistProjects = $bool;
    return $this;
  }

  protected function loadPage() {
    $table = new DifferentialDiff();
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

  protected function willFilterPage(array $diffs) {
    $revision_ids = array_filter(mpull($diffs, 'getRevisionID'));

    $revisions = array();
    if ($revision_ids) {
      $revisions = id(new DifferentialRevisionQuery())
        ->setViewer($this->getViewer())
        ->withIDs($revision_ids)
        ->execute();
    }

    foreach ($diffs as $key => $diff) {
      if (!$diff->getRevisionID()) {
        continue;
      }

      $revision = idx($revisions, $diff->getRevisionID());
      if ($revision) {
        $diff->attachRevision($revision);
        continue;
      }

      unset($diffs[$key]);
    }


    if ($diffs && $this->needChangesets) {
      $diffs = $this->loadChangesets($diffs);
    }

    if ($diffs && $this->needArcanistProjects) {
      $diffs = $this->loadArcanistProjects($diffs);
    }

    return $diffs;
  }

  private function loadChangesets(array $diffs) {
    id(new DifferentialChangesetQuery())
      ->setViewer($this->getViewer())
      ->setParentQuery($this)
      ->withDiffs($diffs)
      ->needAttachToDiffs(true)
      ->needHunks(true)
      ->execute();

    return $diffs;
  }

  private function loadArcanistProjects(array $diffs) {
    $phids = array_filter(mpull($diffs, 'getArcanistProjectPHID'));
    $projects = array();
    $project_map = array();
    if ($phids) {
      $projects = id(new PhabricatorRepositoryArcanistProject())
        ->loadAllWhere(
          'phid IN (%Ls)',
          $phids);
      $project_map = mpull($projects, null, 'getPHID');
    }

    foreach ($diffs as $diff) {
      $project = null;
      if ($diff->getArcanistProjectPHID()) {
        $project = idx($project_map, $diff->getArcanistProjectPHID());
      }
      $diff->attachArcanistProject($project);
    }

    return $diffs;
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

    if ($this->revisionIDs) {
      $where[] = qsprintf(
        $conn_r,
        'revisionID IN (%Ld)',
        $this->revisionIDs);
    }

    $where[] = $this->buildPagingClause($conn_r);
    return $this->formatWhereClause($where);
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorDifferentialApplication';
  }

}
