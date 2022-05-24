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

  public function newResultObject() {
    return new PhabricatorNamedQuery();
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'id IN (%Ld)',
        $this->ids);
    }

    if ($this->engineClassNames !== null) {
      $where[] = qsprintf(
        $conn,
        'engineClassName IN (%Ls)',
        $this->engineClassNames);
    }

    if ($this->userPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'userPHID IN (%Ls)',
        $this->userPHIDs);
    }

    if ($this->queryKeys !== null) {
      $where[] = qsprintf(
        $conn,
        'queryKey IN (%Ls)',
        $this->queryKeys);
    }

    return $where;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorSearchApplication';
  }

}
