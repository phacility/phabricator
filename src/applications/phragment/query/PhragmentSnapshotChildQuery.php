<?php

final class PhragmentSnapshotChildQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $snapshotPHIDs;
  private $fragmentPHIDs;
  private $fragmentVersionPHIDs;
  private $needFragments;
  private $needFragmentVersions;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withSnapshotPHIDs(array $snapshot_phids) {
    $this->snapshotPHIDs = $snapshot_phids;
    return $this;
  }

  public function withFragmentPHIDs(array $fragment_phids) {
    $this->fragmentPHIDs = $fragment_phids;
    return $this;
  }

  public function withFragmentVersionPHIDs(array $fragment_version_phids) {
    $this->fragmentVersionPHIDs = $fragment_version_phids;
    return $this;
  }

  public function needFragments($need_fragments) {
    $this->needFragments = $need_fragments;
    return $this;
  }

  public function needFragmentVersions($need_fragment_versions) {
    $this->needFragmentVersions = $need_fragment_versions;
    return $this;
  }

  protected function loadPage() {
    $table = new PhragmentSnapshotChild();
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

  protected function buildWhereClause(AphrontDatabaseConnection $conn_r) {
    $where = array();

    if ($this->ids) {
      $where[] = qsprintf(
        $conn_r,
        'id IN (%Ld)',
        $this->ids);
    }

    if ($this->snapshotPHIDs) {
      $where[] = qsprintf(
        $conn_r,
        'snapshotPHID IN (%Ls)',
        $this->snapshotPHIDs);
    }

    if ($this->fragmentPHIDs) {
      $where[] = qsprintf(
        $conn_r,
        'fragmentPHID IN (%Ls)',
        $this->fragmentPHIDs);
    }

    if ($this->fragmentVersionPHIDs) {
      $where[] = qsprintf(
        $conn_r,
        'fragmentVersionPHID IN (%Ls)',
        $this->fragmentVersionPHIDs);
    }

    $where[] = $this->buildPagingClause($conn_r);

    return $this->formatWhereClause($where);
  }

  protected function willFilterPage(array $page) {
    $snapshots = array();

    $snapshot_phids = array_filter(mpull($page, 'getSnapshotPHID'));
    if ($snapshot_phids) {
      $snapshots = id(new PhabricatorObjectQuery())
        ->setViewer($this->getViewer())
        ->withPHIDs($snapshot_phids)
        ->setParentQuery($this)
        ->execute();
      $snapshots = mpull($snapshots, null, 'getPHID');
    }

    foreach ($page as $key => $child) {
      $snapshot_phid = $child->getSnapshotPHID();
      if (empty($snapshots[$snapshot_phid])) {
        unset($page[$key]);
        continue;
      }
      $child->attachSnapshot($snapshots[$snapshot_phid]);
    }

    return $page;
  }

  protected function didFilterPage(array $page) {
    if ($this->needFragments) {
      $fragments = array();

      $fragment_phids = array_filter(mpull($page, 'getFragmentPHID'));
      if ($fragment_phids) {
        $fragments = id(new PhabricatorObjectQuery())
          ->setViewer($this->getViewer())
          ->withPHIDs($fragment_phids)
          ->setParentQuery($this)
          ->execute();
        $fragments = mpull($fragments, null, 'getPHID');
      }

      foreach ($page as $key => $child) {
        $fragment_phid = $child->getFragmentPHID();
        if (empty($fragments[$fragment_phid])) {
          unset($page[$key]);
          continue;
        }
        $child->attachFragment($fragments[$fragment_phid]);
      }
    }

    if ($this->needFragmentVersions) {
      $fragment_versions = array();

      $fragment_version_phids = array_filter(mpull(
        $page,
        'getFragmentVersionPHID'));
      if ($fragment_version_phids) {
        $fragment_versions = id(new PhabricatorObjectQuery())
          ->setViewer($this->getViewer())
          ->withPHIDs($fragment_version_phids)
          ->setParentQuery($this)
          ->execute();
        $fragment_versions = mpull($fragment_versions, null, 'getPHID');
      }

      foreach ($page as $key => $child) {
        $fragment_version_phid = $child->getFragmentVersionPHID();
        if (empty($fragment_versions[$fragment_version_phid])) {
          continue;
        }
        $child->attachFragmentVersion(
          $fragment_versions[$fragment_version_phid]);
      }
    }

    return $page;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorPhragmentApplication';
  }
}
