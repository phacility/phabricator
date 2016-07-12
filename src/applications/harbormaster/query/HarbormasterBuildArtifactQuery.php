<?php

final class HarbormasterBuildArtifactQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $buildTargetPHIDs;
  private $artifactTypes;
  private $artifactIndexes;
  private $keyBuildPHID;
  private $keyBuildGeneration;
  private $isReleased;

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

  public function withArtifactIndexes(array $artifact_indexes) {
    $this->artifactIndexes = $artifact_indexes;
    return $this;
  }

  public function withIsReleased($released) {
    $this->isReleased = $released;
    return $this;
  }

  public function newResultObject() {
    return new HarbormasterBuildArtifact();
  }

  protected function loadPage() {
    return $this->loadStandardPage($this->newResultObject());
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

    if ($this->buildTargetPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'buildTargetPHID IN (%Ls)',
        $this->buildTargetPHIDs);
    }

    if ($this->artifactTypes !== null) {
      $where[] = qsprintf(
        $conn,
        'artifactType in (%Ls)',
        $this->artifactTypes);
    }

    if ($this->artifactIndexes !== null) {
      $where[] = qsprintf(
        $conn,
        'artifactIndex IN (%Ls)',
        $this->artifactIndexes);
    }

    if ($this->isReleased !== null) {
      $where[] = qsprintf(
        $conn,
        'isReleased = %d',
        (int)$this->isReleased);
    }

    return $where;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorHarbormasterApplication';
  }

}
