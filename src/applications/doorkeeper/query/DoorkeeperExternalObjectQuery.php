<?php

final class DoorkeeperExternalObjectQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  protected $phids;
  protected $objectKeys;

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withObjectKeys(array $keys) {
    $this->objectKeys = $keys;
    return $this;
  }

  public function newResultObject() {
    return new DoorkeeperExternalObject();
  }

  protected function loadPage() {
    return $this->loadStandardPage($this->newResultObject());
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn,
        'phid IN (%Ls)',
        $this->phids);
    }

    if ($this->objectKeys !== null) {
      $where[] = qsprintf(
        $conn,
        'objectKey IN (%Ls)',
        $this->objectKeys);
    }

    return $where;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorDoorkeeperApplication';
  }

}
