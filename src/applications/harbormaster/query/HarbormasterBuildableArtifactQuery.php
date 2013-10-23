<?php

final class HarbormasterBuildableArtifactQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $buildablePHIDs;
  private $artifactTypes;
  private $artifactKeys;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withBuildablePHIDs(array $buildable_phids) {
    $this->buildablePHIDs = $buildable_phids;
    return $this;
  }

  public function withArtifactTypes(array $artifact_types) {
    $this->artifactTypes = $artifact_types;
    return $this;
  }

  public function withArtifactKeys(array $artifact_keys) {
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
    $buildables = array();

    $buildable_phids = array_filter(mpull($page, 'getBuildablePHID'));
    if ($buildable_phids) {
      $buildables = id(new PhabricatorObjectQuery())
        ->setViewer($this->getViewer())
        ->withPHIDs($buildable_phids)
        ->setParentQuery($this)
        ->execute();
      $buildables = mpull($buildables, null, 'getPHID');
    }

    foreach ($page as $key => $artifact) {
      $buildable_phid = $artifact->getBuildablePHID();
      if (empty($buildables[$buildable_phid])) {
        unset($page[$key]);
        continue;
      }
      $artifact->attachBuildable($buildables[$buildable_phid]);
    }

    return $page;
  }

  private function buildWhereClause(AphrontDatabaseConnection $conn_r) {
    $where = array();

    if ($this->ids) {
      $where[] = qsprintf(
        $conn_r,
        'id IN (%Ld)',
        $this->ids);
    }

    if ($this->buildablePHIDs) {
      $where[] = qsprintf(
        $conn_r,
        'buildablePHID IN (%Ls)',
        $this->buildablePHIDs);
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
        $indexes[] = PhabricatorHash::digestForIndex($key);
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
    return 'PhabricatorApplicationHarbormaster';
  }

}
