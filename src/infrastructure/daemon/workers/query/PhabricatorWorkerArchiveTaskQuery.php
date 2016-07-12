<?php

final class PhabricatorWorkerArchiveTaskQuery
  extends PhabricatorQuery {

  private $ids;
  private $dateModifiedSince;
  private $dateCreatedBefore;
  private $objectPHIDs;
  private $limit;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withDateModifiedSince($timestamp) {
    $this->dateModifiedSince = $timestamp;
    return $this;
  }

  public function withDateCreatedBefore($timestamp) {
    $this->dateCreatedBefore = $timestamp;
    return $this;
  }

  public function withObjectPHIDs(array $phids) {
    $this->objectPHIDs = $phids;
    return $this;
  }

  public function setLimit($limit) {
    $this->limit = $limit;
    return $this;
  }

  public function execute() {
    $task_table = new PhabricatorWorkerArchiveTask();

    $conn_r = $task_table->establishConnection('r');

    $rows = queryfx_all(
      $conn_r,
      'SELECT * FROM %T %Q %Q %Q',
      $task_table->getTableName(),
      $this->buildWhereClause($conn_r),
      $this->buildOrderClause($conn_r),
      $this->buildLimitClause($conn_r));

    return $task_table->loadAllFromArray($rows);
  }

  protected function buildWhereClause(AphrontDatabaseConnection $conn_r) {
    $where = array();

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn_r,
        'id in (%Ld)',
        $this->ids);
    }

    if ($this->objectPHIDs !== null) {
      $where[] = qsprintf(
        $conn_r,
        'objectPHID IN (%Ls)',
        $this->objectPHIDs);
    }

    if ($this->dateModifiedSince) {
      $where[] = qsprintf(
        $conn_r,
        'dateModified > %d',
        $this->dateModifiedSince);
    }

    if ($this->dateCreatedBefore) {
      $where[] = qsprintf(
        $conn_r,
        'dateCreated < %d',
        $this->dateCreatedBefore);
    }

    return $this->formatWhereClause($where);
  }

  private function buildOrderClause(AphrontDatabaseConnection $conn_r) {
    // NOTE: The garbage collector executes this query with a date constraint,
    // and the query is inefficient if we don't use the same key for ordering.
    // See T9808 for discussion.

    if ($this->dateCreatedBefore) {
      return qsprintf($conn_r, 'ORDER BY dateCreated DESC, id DESC');
    } else {
      return qsprintf($conn_r, 'ORDER BY id DESC');
    }
  }

  private function buildLimitClause(AphrontDatabaseConnection $conn_r) {
    $clause =  '';
    if ($this->limit) {
      $clause = qsprintf($conn_r, 'LIMIT %d', $this->limit);
    }
    return $clause;
  }

}
