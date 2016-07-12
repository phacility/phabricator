<?php

final class PhabricatorNamedQueryQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $engineClassNames;
  private $userPHIDs;
  private $queryKeys;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withUserPHIDs(array $user_phids) {
    $this->userPHIDs = $user_phids;
    return $this;
  }

  public function withEngineClassNames(array $engine_class_names) {
    $this->engineClassNames = $engine_class_names;
    return $this;
  }

  public function withQueryKeys(array $query_keys) {
    $this->queryKeys = $query_keys;
    return $this;
  }

  protected function loadPage() {
    $table = new PhabricatorNamedQuery();
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

    if ($this->ids) {
      $where[] = qsprintf(
        $conn_r,
        'id IN (%Ld)',
        $this->ids);
    }

    if ($this->engineClassNames) {
      $where[] = qsprintf(
        $conn_r,
        'engineClassName IN (%Ls)',
        $this->engineClassNames);
    }

    if ($this->userPHIDs) {
      $where[] = qsprintf(
        $conn_r,
        'userPHID IN (%Ls)',
        $this->userPHIDs);
    }

    if ($this->queryKeys) {
      $where[] = qsprintf(
        $conn_r,
        'queryKey IN (%Ls)',
        $this->queryKeys);
    }

    $where[] = $this->buildPagingClause($conn_r);

    return $this->formatWhereClause($where);
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorSearchApplication';
  }

}
