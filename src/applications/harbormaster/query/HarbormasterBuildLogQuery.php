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

  public function newResultObject() {
    return new HarbormasterBuildLog();
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

    if ($this->buildTargetPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'buildTargetPHID IN (%Ls)',
        $this->buildTargetPHIDs);
    }

    return $where;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorHarbormasterApplication';
  }

}
