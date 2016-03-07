<?php

final class DifferentialDiffQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $revisionIDs;

  private $needChangesets = false;
  private $needProperties;

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

  public function needProperties($need_properties) {
    $this->needProperties = $need_properties;
    return $this;
  }

  public function newResultObject() {
    return new DifferentialDiff();
  }

  protected function loadPage() {
    return $this->loadStandardPage($this->newResultObject());
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

    return $diffs;
  }

  protected function didFilterPage(array $diffs) {
    if ($this->needProperties) {
      $properties = id(new DifferentialDiffProperty())->loadAllWhere(
        'diffID IN (%Ld)',
        mpull($diffs, 'getID'));

      $properties = mgroup($properties, 'getDiffID');
      foreach ($diffs as $diff) {
        $map = idx($properties, $diff->getID(), array());
        $map = mpull($map, 'getData', 'getName');
        $diff->attachDiffProperties($map);
      }
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

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->ids) {
      $where[] = qsprintf(
        $conn,
        'id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids) {
      $where[] = qsprintf(
        $conn,
        'phid IN (%Ls)',
        $this->phids);
    }

    if ($this->revisionIDs) {
      $where[] = qsprintf(
        $conn,
        'revisionID IN (%Ld)',
        $this->revisionIDs);
    }

    return $where;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorDifferentialApplication';
  }

}
