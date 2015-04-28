<?php

final class PhabricatorDashboardPanelQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $archived;
  private $panelTypes;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withArchived($archived) {
    $this->archived = $archived;
    return $this;
  }

  public function withPanelTypes(array $types) {
    $this->panelTypes = $types;
    return $this;
  }

  protected function loadPage() {
    $table = new PhabricatorDashboardPanel();
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

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn_r,
        'id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn_r,
        'phid IN (%Ls)',
        $this->phids);
    }

    if ($this->archived !== null) {
      $where[] = qsprintf(
        $conn_r,
        'isArchived = %d',
        (int)$this->archived);
    }

    if ($this->panelTypes !== null) {
      $where[] = qsprintf(
        $conn_r,
        'panelType IN (%Ls)',
        $this->panelTypes);
    }

    $where[] = $this->buildPagingClause($conn_r);

    return $this->formatWhereClause($where);
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorDashboardApplication';
  }

}
