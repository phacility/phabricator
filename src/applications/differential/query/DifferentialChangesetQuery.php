<?php

final class DifferentialChangesetQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $diffs;

  private $needAttachToDiffs;
  private $needHunks;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withDiffs(array $diffs) {
    assert_instances_of($diffs, 'DifferentialDiff');
    $this->diffs = $diffs;
    return $this;
  }

  public function needAttachToDiffs($attach) {
    $this->needAttachToDiffs = $attach;
    return $this;
  }

  public function needHunks($need) {
    $this->needHunks = $need;
    return $this;
  }

  protected function willExecute() {
    // If we fail to load any changesets (which is possible in the case of an
    // empty commit) we'll never call didFilterPage(). Attach empty changeset
    // lists now so that we end up with the right result.
    if ($this->needAttachToDiffs) {
      foreach ($this->diffs as $diff) {
        $diff->attachChangesets(array());
      }
    }
  }

  protected function loadPage() {
    $table = new DifferentialChangeset();
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

  protected function willFilterPage(array $changesets) {
    // First, attach all the diffs we already have. We can just do this
    // directly without worrying about querying for them. When we don't have
    // a diff, record that we need to load it.
    if ($this->diffs) {
      $have_diffs = mpull($this->diffs, null, 'getID');
    } else {
      $have_diffs = array();
    }

    $must_load = array();
    foreach ($changesets as $key => $changeset) {
      $diff_id = $changeset->getDiffID();
      if (isset($have_diffs[$diff_id])) {
        $changeset->attachDiff($have_diffs[$diff_id]);
      } else {
        $must_load[$key] = $changeset;
      }
    }

    // Load all the diffs we don't have.
    $need_diff_ids = mpull($must_load, 'getDiffID');
    $more_diffs = array();
    if ($need_diff_ids) {
      $more_diffs = id(new DifferentialDiffQuery())
        ->setViewer($this->getViewer())
        ->setParentQuery($this)
        ->withIDs($need_diff_ids)
        ->execute();
      $more_diffs = mpull($more_diffs, null, 'getID');
    }

    // Attach the diffs we loaded.
    foreach ($must_load as $key => $changeset) {
      $diff_id = $changeset->getDiffID();
      if (isset($more_diffs[$diff_id])) {
        $changeset->attachDiff($more_diffs[$diff_id]);
      } else {
        // We didn't have the diff, and could not load it (it does not exist,
        // or we can't see it), so filter this result out.
        unset($changesets[$key]);
      }
    }

    return $changesets;
  }

  protected function didFilterPage(array $changesets) {
    if ($this->needAttachToDiffs) {
      $changeset_groups = mgroup($changesets, 'getDiffID');
      foreach ($this->diffs as $diff) {
        $diff_changesets = idx($changeset_groups, $diff->getID(), array());
        $diff->attachChangesets($diff_changesets);
      }
    }

    if ($this->needHunks) {
      id(new DifferentialHunkQuery())
        ->setViewer($this->getViewer())
        ->setParentQuery($this)
        ->withChangesets($changesets)
        ->needAttachToChangesets(true)
        ->execute();
    }

    return $changesets;
  }

  protected function buildWhereClause(AphrontDatabaseConnection $conn_r) {
    $where = array();

    if ($this->diffs !== null) {
      $where[] = qsprintf(
        $conn_r,
        'diffID IN (%Ld)',
        mpull($this->diffs, 'getID'));
    }

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn_r,
        'id IN (%Ld)',
        $this->ids);
    }

    $where[] = $this->buildPagingClause($conn_r);

    return $this->formatWhereClause($where);
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorDifferentialApplication';
  }

}
