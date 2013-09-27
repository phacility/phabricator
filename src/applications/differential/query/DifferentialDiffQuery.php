<?php

final class DifferentialDiffQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $revisionIDs;
  private $needChangesets = false;
  private $needArcanistProjects = false;

  public function withIDs(array $ids) {
    $this->ids = $ids;
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

  public function loadPage() {
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

  public function willFilterPage(array $diffs) {
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
        $diff->attachRevision(null);
        continue;
      }

      $revision = idx($revisions, $diff->getRevisionID());
      if ($revision) {
        $diff->attachRevision($revision);
        continue;
      }

      unset($diffs[$key]);
    }


    if ($this->needChangesets) {
      $this->loadChangesets($diffs);
    }

    if ($this->needArcanistProjects) {
      $this->loadArcanistProjects($diffs);
    }

    return $diffs;
  }

  private function loadChangesets(array $diffs) {
    foreach ($diffs as $diff) {
      $diff->attachChangesets(
        $diff->loadRelatives(new DifferentialChangeset(), 'diffID'));
      foreach ($diff->getChangesets() as $changeset) {
        $changeset->attachHunks(
          $changeset->loadRelatives(new DifferentialHunk(), 'changesetID'));
      }
    }
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

  private function buildWhereClause(AphrontDatabaseConnection $conn_r) {
    $where = array();

    if ($this->ids) {
      $where[] = qsprintf(
        $conn_r,
        'id IN (%Ld)',
        $this->ids);
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

}
