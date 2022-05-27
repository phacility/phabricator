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

  public function newResultObject() {
    return new HarbormasterBuildStep();
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
        'phid in (%Ls)',
        $this->phids);
    }

    if ($this->buildPlanPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'buildPlanPHID in (%Ls)',
        $this->buildPlanPHIDs);
    }

    return $where;
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
