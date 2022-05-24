<?php

final class PhabricatorAuthContactNumberQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $objectPHIDs;
  private $statuses;
  private $uniqueKeys;
  private $isPrimary;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withObjectPHIDs(array $object_phids) {
    $this->objectPHIDs = $object_phids;
    return $this;
  }

  public function withStatuses(array $statuses) {
    $this->statuses = $statuses;
    return $this;
  }

  public function withUniqueKeys(array $unique_keys) {
    $this->uniqueKeys = $unique_keys;
    return $this;
  }

  public function withIsPrimary($is_primary) {
    $this->isPrimary = $is_primary;
    return $this;
  }

  public function newResultObject() {
    return new PhabricatorAuthContactNumber();
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

    if ($this->objectPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'objectPHID IN (%Ls)',
        $this->objectPHIDs);
    }

    if ($this->statuses !== null) {
      $where[] = qsprintf(
        $conn,
        'status IN (%Ls)',
        $this->statuses);
    }

    if ($this->uniqueKeys !== null) {
      $where[] = qsprintf(
        $conn,
        'uniqueKey IN (%Ls)',
        $this->uniqueKeys);
    }

    if ($this->isPrimary !== null) {
      $where[] = qsprintf(
        $conn,
        'isPrimary = %d',
        (int)$this->isPrimary);
    }

    return $where;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorAuthApplication';
  }

}
