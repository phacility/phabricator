<?php

final class AlmanacDeviceQuery
  extends AlmanacQuery {

  private $ids;
  private $phids;
  private $names;
  private $namePrefix;
  private $nameSuffix;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withNames(array $names) {
    $this->names = $names;
    return $this;
  }

  public function withNamePrefix($prefix) {
    $this->namePrefix = $prefix;
    return $this;
  }

  public function withNameSuffix($suffix) {
    $this->nameSuffix = $suffix;
    return $this;
  }

  protected function loadPage() {
    $table = new AlmanacDevice();
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

    if ($this->names !== null) {
      $hashes = array();
      foreach ($this->names as $name) {
        $hashes[] = PhabricatorHash::digestForIndex($name);
      }
      $where[] = qsprintf(
        $conn_r,
        'nameIndex IN (%Ls)',
        $hashes);
    }

    if ($this->namePrefix !== null) {
      $where[] = qsprintf(
        $conn_r,
        'name LIKE %>',
        $this->namePrefix);
    }

    if ($this->nameSuffix !== null) {
      $where[] = qsprintf(
        $conn_r,
        'name LIKE %<',
        $this->nameSuffix);
    }

    $where[] = $this->buildPagingClause($conn_r);

    return $this->formatWhereClause($where);
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorAlmanacApplication';
  }

}
