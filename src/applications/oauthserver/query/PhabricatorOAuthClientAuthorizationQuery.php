<?php

final class PhabricatorOAuthClientAuthorizationQuery
extends PhabricatorOffsetPagedQuery {
  private $userPHIDs;

  public function withUserPHIDs(array $phids) {
    $this->userPHIDs = $phids;
    return $this;
  }
  private function getUserPHIDs() {
    return $this->userPHIDs;
  }

  public function execute() {
    $table  = new PhabricatorOAuthClientAuthorization();
    $conn_r = $table->establishConnection('r');

    $where_clause = $this->buildWhereClause($conn_r);
    $limit_clause = $this->buildLimitClause($conn_r);

    $data = queryfx_all(
      $conn_r,
      'SELECT * FROM %T auth %Q %Q',
      $table->getTableName(),
      $where_clause,
      $limit_clause);

    return $table->loadAllFromArray($data);
  }

  private function buildWhereClause($conn_r) {
    $where = array();

    if ($this->getUserPHIDs()) {
      $where[] = qsprintf(
        $conn_r,
        'userPHID IN (%Ls)',
        $this->getUserPHIDs());
    }

    return $this->formatWhereClause($where);
  }
}
