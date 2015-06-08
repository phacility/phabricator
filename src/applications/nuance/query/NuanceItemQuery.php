<?php

final class NuanceItemQuery
  extends NuanceQuery {

  private $ids;
  private $phids;
  private $sourcePHIDs;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withSourcePHIDs($source_phids) {
    $this->sourcePHIDs = $source_phids;
    return $this;
  }

  protected function loadPage() {
    $table = new NuanceItem();
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

    if ($this->sourcePHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'sourcePHID IN (%Ls)',
        $this->sourcePHIDs);
    }

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
