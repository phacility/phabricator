<?php

final class PhabricatorAuthTemporaryTokenQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $objectPHIDs;
  private $tokenTypes;
  private $expired;
  private $tokenCodes;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withObjectPHIDs(array $object_phids) {
    $this->objectPHIDs = $object_phids;
    return $this;
  }

  public function withTokenTypes(array $types) {
    $this->tokenTypes = $types;
    return $this;
  }

  public function withExpired($expired) {
    $this->expired = $expired;
    return $this;
  }

  public function withTokenCodes(array $codes) {
    $this->tokenCodes = $codes;
    return $this;
  }

  protected function loadPage() {
    $table = new PhabricatorAuthTemporaryToken();
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

    if ($this->objectPHIDs !== null) {
      $where[] = qsprintf(
        $conn_r,
        'objectPHID IN (%Ls)',
        $this->objectPHIDs);
    }

    if ($this->tokenTypes !== null) {
      $where[] = qsprintf(
        $conn_r,
        'tokenType IN (%Ls)',
        $this->tokenTypes);
    }

    if ($this->expired !== null) {
      if ($this->expired) {
        $where[] = qsprintf(
          $conn_r,
          'tokenExpires <= %d',
          time());
      } else {
        $where[] = qsprintf(
          $conn_r,
          'tokenExpires > %d',
          time());
      }
    }

    if ($this->tokenCodes !== null) {
      $where[] = qsprintf(
        $conn_r,
        'tokenCode IN (%Ls)',
        $this->tokenCodes);
    }

    $where[] = $this->buildPagingClause($conn_r);

    return $this->formatWhereClause($where);
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorAuthApplication';
  }

}
