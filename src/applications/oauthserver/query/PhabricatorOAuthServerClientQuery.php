<?php

final class PhabricatorOAuthServerClientQuery
extends PhabricatorOffsetPagedQuery {
  private $creatorPHIDs;

  public function withCreatorPHIDs(array $phids) {
    $this->creatorPHIDs = $phids;
    return $this;
  }
  private function getCreatorPHIDs() {
    return $this->creatorPHIDs;
  }

  public function execute() {
    $table  = new PhabricatorOAuthServerClient();
    $conn_r = $table->establishConnection('r');

    $where_clause = $this->buildWhereClause($conn_r);
    $limit_clause = $this->buildLimitClause($conn_r);

    $data = queryfx_all(
      $conn_r,
      'SELECT * FROM %T client %Q %Q',
      $table->getTableName(),
      $where_clause,
      $limit_clause);

    return $table->loadAllFromArray($data);
  }

  private function buildWhereClause($conn_r) {
    $where = array();

    if ($this->getCreatorPHIDs()) {
      $where[] = qsprintf(
        $conn_r,
        'creatorPHID IN (%Ls)',
        $this->getCreatorPHIDs());
    }

    return $this->formatWhereClause($where);
  }
}
