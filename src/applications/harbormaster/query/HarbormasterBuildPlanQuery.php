<?php

final class HarbormasterBuildPlanQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $statuses;
  private $datasourceQuery;

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

  protected function loadPage() {
    $table = new HarbormasterBuildPlan();
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

    if ($this->statuses) {
      $where[] = qsprintf(
        $conn_r,
        'planStatus IN (%Ls)',
        $this->statuses);
    }

    if (strlen($this->datasourceQuery)) {
      $where[] = qsprintf(
        $conn_r,
        'name LIKE %>',
        $this->datasourceQuery);
    }

    $where[] = $this->buildPagingClause($conn_r);

    return $this->formatWhereClause($where);
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
