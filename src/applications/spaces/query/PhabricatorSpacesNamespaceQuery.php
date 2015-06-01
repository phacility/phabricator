<?php

final class PhabricatorSpacesNamespaceQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $isDefaultNamespace;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withIsDefaultNamespace($default) {
    $this->isDefaultNamespace = $default;
    return $this;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorApplicationSpaces';
  }

  protected function loadPage() {
    $table = new PhabricatorSpacesNamespace();
    $conn_r = $table->establishConnection('r');

    $rows = queryfx_all(
      $conn_r,
      'SELECT * FROM %T %Q %Q %Q',
      $table->getTableName(),
      $this->buildWhereClause($conn_r),
      $this->buildOrderClause($conn_r),
      $this->buildLimitClause($conn_r));

    return $table->loadAllFromArray($rows);
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

    if ($this->isDefaultNamespace !== null) {
      if ($this->isDefaultNamespace) {
        $where[] = qsprintf(
          $conn_r,
          'isDefaultNamespace = 1');
      } else {
        $where[] = qsprintf(
          $conn_r,
          'isDefaultNamespace IS NULL');
      }
    }

    $where[] = $this->buildPagingClause($conn_r);
    return $this->formatWhereClause($where);
  }

}
