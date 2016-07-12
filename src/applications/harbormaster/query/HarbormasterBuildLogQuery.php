<?php

final class HarbormasterBuildLogQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $buildPHIDs;
  private $buildTargetPHIDs;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withBuildTargetPHIDs(array $build_target_phids) {
    $this->buildTargetPHIDs = $build_target_phids;
    return $this;
  }

  protected function loadPage() {
    $table = new HarbormasterBuildLog();
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
    $build_targets = array();

    $build_target_phids = array_filter(mpull($page, 'getBuildTargetPHID'));
    if ($build_target_phids) {
      $build_targets = id(new HarbormasterBuildTargetQuery())
        ->setViewer($this->getViewer())
        ->withPHIDs($build_target_phids)
        ->setParentQuery($this)
        ->execute();
      $build_targets = mpull($build_targets, null, 'getPHID');
    }

    foreach ($page as $key => $build_log) {
      $build_target_phid = $build_log->getBuildTargetPHID();
      if (empty($build_targets[$build_target_phid])) {
        unset($page[$key]);
        continue;
      }
      $build_log->attachBuildTarget($build_targets[$build_target_phid]);
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

    if ($this->buildTargetPHIDs) {
      $where[] = qsprintf(
        $conn_r,
        'buildTargetPHID IN (%Ls)',
        $this->buildTargetPHIDs);
    }

    $where[] = $this->buildPagingClause($conn_r);

    return $this->formatWhereClause($where);
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorHarbormasterApplication';
  }

}
