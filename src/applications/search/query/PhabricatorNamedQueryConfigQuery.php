<?php

final class PhabricatorNamedQueryConfigQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $engineClassNames;
  private $scopePHIDs;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withScopePHIDs(array $scope_phids) {
    $this->scopePHIDs = $scope_phids;
    return $this;
  }

  public function withEngineClassNames(array $engine_class_names) {
    $this->engineClassNames = $engine_class_names;
    return $this;
  }

  public function newResultObject() {
    return new PhabricatorNamedQueryConfig();
  }

  protected function loadPage() {
    return $this->loadStandardPage($this->newResultObject());
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

    if ($this->scopePHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'scopePHID IN (%Ls)',
        $this->scopePHIDs);
    }

    return $where;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorSearchApplication';
  }

}
