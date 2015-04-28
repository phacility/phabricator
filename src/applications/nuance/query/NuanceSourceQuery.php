<?php

final class NuanceSourceQuery
  extends NuanceQuery {

  private $ids;
  private $phids;
  private $creatorPHIDs;
  private $types;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withCreatorPHIDs(array $phids) {
    $this->CreatorPHIDs = $phids;
    return $this;
  }

  public function withTypes($types) {
    $this->types = $types;
    return $this;
  }


  protected function loadPage() {
    $table = new NuanceSource();
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

    $where[] = $this->buildPagingClause($conn_r);

    if ($this->creatorPHIDs) {
      $where[] = qsprintf(
        $conn_r,
        'creatorPHID IN (%Ls)',
        $this->creatorPHIDs);
    }

    if ($this->types) {
      $where[] = qsprintf(
        $conn_r,
        'type IN (%Ld)',
        $this->types);
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
