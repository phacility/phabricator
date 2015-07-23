<?php

final class NuanceQueueQuery
  extends NuanceQuery {

  private $ids;
  private $phids;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  protected function loadPage() {
    $table = new NuanceQueue();
    $conn = $table->establishConnection('r');

    $data = queryfx_all(
      $conn,
      '%Q FROM %T %Q %Q %Q',
      $this->buildSelectClause($conn),
      $table->getTableName(),
      $this->buildWhereClause($conn),
      $this->buildOrderClause($conn),
      $this->buildLimitClause($conn));

    return $table->loadAllFromArray($data);
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

    return $where;
  }

}
