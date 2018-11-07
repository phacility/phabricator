<?php

/**
 * A query class which uses offset/limit paging. Provides logic and accessors
 * for offsets and limits.
 */
abstract class PhabricatorOffsetPagedQuery extends PhabricatorQuery {

  private $offset;
  private $limit;

  final public function setOffset($offset) {
    $this->offset = $offset;
    return $this;
  }

  final public function setLimit($limit) {
    $this->limit = $limit;
    return $this;
  }

  final public function getOffset() {
    return $this->offset;
  }

  final public function getLimit() {
    return $this->limit;
  }

  protected function buildLimitClause(AphrontDatabaseConnection $conn) {
    if ($this->limit && $this->offset) {
      return qsprintf($conn, 'LIMIT %d, %d', $this->offset, $this->limit);
    } else if ($this->limit) {
      return qsprintf($conn, 'LIMIT %d', $this->limit);
    } else if ($this->offset) {
      return qsprintf($conn, 'LIMIT %d, %d', $this->offset, PHP_INT_MAX);
    } else {
      return qsprintf($conn, '');
    }
  }

  final public function executeWithOffsetPager(PHUIPagerView $pager) {
    $this->setLimit($pager->getPageSize() + 1);
    $this->setOffset($pager->getOffset());

    $results = $this->execute();

    return $pager->sliceResults($results);
  }

}
