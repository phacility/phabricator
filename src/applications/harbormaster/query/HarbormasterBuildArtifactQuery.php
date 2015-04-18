<?php

final class HarbormasterBuildArtifactQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $buildTargetPHIDs;
  private $artifactTypes;
  private $artifactKeys;
  private $keyBuildPHID;
  private $keyBuildGeneration;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withBuildTargetPHIDs(array $build_target_phids) {
    $this->buildTargetPHIDs = $build_target_phids;
    return $this;
  }

  public function withArtifactTypes(array $artifact_types) {
    $this->artifactTypes = $artifact_types;
    return $this;
  }

  public function withArtifactKeys(
    $build_phid,
    $build_gen,
    array $artifact_keys) {
    $this->keyBuildPHID = $build_phid;
    $this->keyBuildGeneration = $build_gen;
    $this->artifactKeys = $artifact_keys;
    return $this;
  }

  protected function loadPage() {
    $table = new HarbormasterBuildArtifact();
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

    if ($this->buildTargetPHIDs) {
      $where[] = qsprintf(
        $conn_r,
        'buildTargetPHID IN (%Ls)',
        $this->buildTargetPHIDs);
    }

    if ($this->artifactTypes) {
      $where[] = qsprintf(
        $conn_r,
        'artifactType in (%Ls)',
        $this->artifactTypes);
    }

    if ($this->artifactKeys) {
      $indexes = array();
      foreach ($this->artifactKeys as $key) {
        $indexes[] = PhabricatorHash::digestForIndex(
          $this->keyBuildPHID.$this->keyBuildGeneration.$key);
      }

      $where[] = qsprintf(
        $conn_r,
        'artifactIndex IN (%Ls)',
        $indexes);
    }

    $where[] = $this->buildPagingClause($conn_r);

    return $this->formatWhereClause($where);
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorHarbormasterApplication';
  }

}
