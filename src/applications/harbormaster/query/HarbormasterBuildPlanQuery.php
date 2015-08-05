<?php

final class HarbormasterBuildPlanQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $statuses;
  private $datasourceQuery;
  private $planAutoKeys;
  private $needBuildSteps;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withStatuses(array $statuses) {
    $this->statuses = $statuses;
    return $this;
  }

  public function withDatasourceQuery($query) {
    $this->datasourceQuery = $query;
    return $this;
  }

  public function withPlanAutoKeys(array $keys) {
    $this->planAutoKeys = $keys;
    return $this;
  }

  public function needBuildSteps($need) {
    $this->needBuildSteps = $need;
    return $this;
  }

  public function newResultObject() {
    return new HarbormasterBuildPlan();
  }

  protected function loadPage() {
    return $this->loadStandardPage($this->newResultObject());
  }

  protected function didFilterPage(array $page) {
    if ($this->needBuildSteps) {
      $plan_phids = mpull($page, 'getPHID');

      $steps = id(new HarbormasterBuildStepQuery())
        ->setParentQuery($this)
        ->setViewer($this->getViewer())
        ->withBuildPlanPHIDs($plan_phids)
        ->execute();
      $steps = mgroup($steps, 'getBuildPlanPHID');

      foreach ($page as $plan) {
        $plan_steps = idx($steps, $plan->getPHID(), array());
        $plan->attachBuildSteps($plan_steps);
      }
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

    if ($this->statuses !== null) {
      $where[] = qsprintf(
        $conn,
        'planStatus IN (%Ls)',
        $this->statuses);
    }

    if (strlen($this->datasourceQuery)) {
      $where[] = qsprintf(
        $conn,
        'name LIKE %>',
        $this->datasourceQuery);
    }

    if ($this->planAutoKeys !== null) {
      $where[] = qsprintf(
        $conn,
        'planAutoKey IN (%Ls)',
        $this->planAutoKeys);
    }

    return $where;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorHarbormasterApplication';
  }

  public function getOrderableColumns() {
    return parent::getOrderableColumns() + array(
      'name' => array(
        'column' => 'name',
        'type' => 'string',
        'reverse' => true,
      ),
    );
  }

  protected function getPagingValueMap($cursor, array $keys) {
    $plan = $this->loadCursorObject($cursor);
    return array(
      'id' => $plan->getID(),
      'name' => $plan->getName(),
    );
  }

  public function getBuiltinOrders() {
    return array(
      'name' => array(
        'vector' => array('name', 'id'),
        'name' => pht('Name'),
      ),
    ) + parent::getBuiltinOrders();
  }

}
