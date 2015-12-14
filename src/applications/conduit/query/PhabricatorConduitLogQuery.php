<?php

final class PhabricatorConduitLogQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $methods;

  public function withMethods(array $methods) {
    $this->methods = $methods;
    return $this;
  }

  public function newResultObject() {
    return new PhabricatorConduitMethodCallLog();
  }

  protected function loadPage() {
    return $this->loadStandardPage($this->newResultObject());
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->methods) {
      $where[] = qsprintf(
        $conn,
        'method IN (%Ls)',
        $this->methods);
    }

    return $where;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorConduitApplication';
  }

}
