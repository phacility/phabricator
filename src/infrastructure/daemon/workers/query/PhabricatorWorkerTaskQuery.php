<?php

abstract class PhabricatorWorkerTaskQuery
  extends PhabricatorQuery {

  private $ids;
  private $dateModifiedSince;
  private $dateCreatedBefore;
  private $objectPHIDs;
  private $classNames;
  private $limit;
  private $minFailureCount;
  private $maxFailureCount;

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

  public function withClassNames(array $names) {
    $this->classNames = $names;
    return $this;
  }

  public function withFailureCountBetween($min, $max) {
    $this->minFailureCount = $min;
    $this->maxFailureCount = $max;
    return $this;
  }

  public function setLimit($limit) {
    $this->limit = $limit;
    return $this;
  }

  protected function buildWhereClause(AphrontDatabaseConnection $conn) {
    $where = array();

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'id in (%Ld)',
        $this->ids);
    }

    if ($this->objectPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'objectPHID IN (%Ls)',
        $this->objectPHIDs);
    }

    if ($this->dateModifiedSince !== null) {
      $where[] = qsprintf(
        $conn,
        'dateModified > %d',
        $this->dateModifiedSince);
    }

    if ($this->dateCreatedBefore !== null) {
      $where[] = qsprintf(
        $conn,
        'dateCreated < %d',
        $this->dateCreatedBefore);
    }

    if ($this->classNames !== null) {
      $where[] = qsprintf(
        $conn,
        'taskClass IN (%Ls)',
        $this->classNames);
    }

    if ($this->minFailureCount !== null) {
      $where[] = qsprintf(
        $conn,
        'failureCount >= %d',
        $this->minFailureCount);
    }

    if ($this->maxFailureCount !== null) {
      $where[] = qsprintf(
        $conn,
        'failureCount <= %d',
        $this->maxFailureCount);
    }

    return $this->formatWhereClause($conn, $where);
  }

  protected function buildOrderClause(AphrontDatabaseConnection $conn) {
    // NOTE: The garbage collector executes this query with a date constraint,
    // and the query is inefficient if we don't use the same key for ordering.
    // See T9808 for discussion.

    if ($this->dateCreatedBefore) {
      return qsprintf($conn, 'ORDER BY dateCreated DESC, id DESC');
    } else if ($this->dateModifiedSince) {
      return qsprintf($conn, 'ORDER BY dateModified DESC, id DESC');
    } else {
      return qsprintf($conn, 'ORDER BY id DESC');
    }
  }

  protected function buildLimitClause(AphrontDatabaseConnection $conn) {
    if ($this->limit) {
      return qsprintf($conn, 'LIMIT %d', $this->limit);
    } else {
      return qsprintf($conn, '');
    }
  }

}
