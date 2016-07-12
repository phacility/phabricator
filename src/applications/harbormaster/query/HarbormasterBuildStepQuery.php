<?php

final class HarbormasterBuildStepQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $buildPlanPHIDs;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withBuildPlanPHIDs(array $phids) {
    $this->buildPlanPHIDs = $phids;
    return $this;
  }

  protected function loadPage() {
    $table = new HarbormasterBuildStep();
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
        'phid in (%Ls)',
        $this->phids);
    }

    if ($this->buildPlanPHIDs) {
      $where[] = qsprintf(
        $conn_r,
        'buildPlanPHID in (%Ls)',
        $this->buildPlanPHIDs);
    }

    $where[] = $this->buildPagingClause($conn_r);

    return $this->formatWhereClause($where);
  }

  protected function willFilterPage(array $page) {
    $plans = array();

    $buildplan_phids = array_filter(mpull($page, 'getBuildPlanPHID'));
    if ($buildplan_phids) {
      $plans = id(new PhabricatorObjectQuery())
        ->setViewer($this->getViewer())
        ->withPHIDs($buildplan_phids)
        ->setParentQuery($this)
        ->execute();
      $plans = mpull($plans, null, 'getPHID');
    }

    foreach ($page as $key => $build) {
      $buildable_phid = $build->getBuildPlanPHID();
      if (empty($plans[$buildable_phid])) {
        unset($page[$key]);
        continue;
      }
      $build->attachBuildPlan($plans[$buildable_phid]);
    }

    return $page;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorHarbormasterApplication';
  }

}
