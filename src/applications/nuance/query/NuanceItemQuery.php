<?php

final class NuanceItemQuery
  extends NuanceQuery {

  private $ids;
  private $phids;
  private $sourceIDs;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withSourceIDs($source_ids) {
    $this->sourceIDs = $source_ids;
    return $this;
  }


  protected function loadPage() {
    $table = new NuanceItem();
    $conn_r = $table->establishConnection('r');

    $data = queryfx_all(
      $conn_r,
      'SELECT FROM %T %Q %Q %Q',
      $table->getTableName(),
      $this->buildWhereClause($conn_r),
      $this->buildOrderClause($conn_r),
      $this->buildLimitClause($conn_r));

    return $table->loadAllFromArray($data);
  }

  protected function buildWhereClause(AphrontDatabaseConnection $conn_r) {
    $where = array();

    $where[] = $this->buildPagingClause($conn_r);

    if ($this->sourceID) {
      $where[] = qsprintf(
        $conn_r,
        'sourceID IN (%Ld)',
        $this->sourceIDs);
    }

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

    return $this->formatWhereClause($where);
  }

}
