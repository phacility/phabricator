<?php

final class PhabricatorPackagesPublisherQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $publisherKeys;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withPublisherKeys(array $keys) {
    $this->publisherKeys = $keys;
    return $this;
  }

  public function newResultObject() {
    return new PhabricatorPackagesPublisher();
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

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn,
        'phid IN (%Ls)',
        $this->phids);
    }

    if ($this->publisherKeys !== null) {
      $where[] = qsprintf(
        $conn,
        'publisherKey IN (%Ls)',
        $this->publisherKeys);
    }

    return $where;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorPackagesApplication';
  }

}
