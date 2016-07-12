<?php

final class PhragmentFragmentQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $paths;
  private $leadingPath;
  private $depths;
  private $needLatestVersion;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withPaths(array $paths) {
    $this->paths = $paths;
    return $this;
  }

  public function withLeadingPath($path) {
    $this->leadingPath = $path;
    return $this;
  }

  public function withDepths($depths) {
    $this->depths = $depths;
    return $this;
  }

  public function needLatestVersion($need_latest_version) {
    $this->needLatestVersion = $need_latest_version;
    return $this;
  }

  protected function loadPage() {
    $table = new PhragmentFragment();
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

    if ($this->phids) {
      $where[] = qsprintf(
        $conn_r,
        'phid IN (%Ls)',
        $this->phids);
    }

    if ($this->paths) {
      $where[] = qsprintf(
        $conn_r,
        'path IN (%Ls)',
        $this->paths);
    }

    if ($this->leadingPath) {
      $where[] = qsprintf(
        $conn_r,
        'path LIKE %>',
        $this->leadingPath);
    }

    if ($this->depths) {
      $where[] = qsprintf(
        $conn_r,
        'depth IN (%Ld)',
        $this->depths);
    }

    $where[] = $this->buildPagingClause($conn_r);

    return $this->formatWhereClause($where);
  }

  protected function didFilterPage(array $page) {
    if ($this->needLatestVersion) {
      $versions = array();

      $version_phids = array_filter(mpull($page, 'getLatestVersionPHID'));
      if ($version_phids) {
        $versions = id(new PhabricatorObjectQuery())
          ->setViewer($this->getViewer())
          ->withPHIDs($version_phids)
          ->setParentQuery($this)
          ->execute();
        $versions = mpull($versions, null, 'getPHID');
      }

      foreach ($page as $key => $fragment) {
        $version_phid = $fragment->getLatestVersionPHID();
        if (empty($versions[$version_phid])) {
          continue;
        }
        $fragment->attachLatestVersion($versions[$version_phid]);
      }
    }

    return $page;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorPhragmentApplication';
  }
}
