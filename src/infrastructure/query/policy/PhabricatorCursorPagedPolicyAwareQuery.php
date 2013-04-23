<?php

/**
 * A query class which uses cursor-based paging. This paging is much more
 * performant than offset-based paging in the presence of policy filtering.
 */
abstract class PhabricatorCursorPagedPolicyAwareQuery
  extends PhabricatorPolicyAwareQuery {

  private $afterID;
  private $beforeID;

  protected function getPagingColumn() {
    return 'id';
  }

  protected function getPagingValue($result) {
    return $result->getID();
  }

  protected function getReversePaging() {
    return false;
  }

  protected function nextPage(array $page) {
    if ($this->beforeID) {
      $this->beforeID = $this->getPagingValue(last($page));
    } else {
      $this->afterID = $this->getPagingValue(last($page));
    }
  }

  final public function setAfterID($object_id) {
    $this->afterID = $object_id;
    return $this;
  }

  final protected function getAfterID() {
    return $this->afterID;
  }

  final public function setBeforeID($object_id) {
    $this->beforeID = $object_id;
    return $this;
  }

  final protected function getBeforeID() {
    return $this->beforeID;
  }

  final protected function buildLimitClause(AphrontDatabaseConnection $conn_r) {
    if ($this->getRawResultLimit()) {
      return qsprintf($conn_r, 'LIMIT %d', $this->getRawResultLimit());
    } else {
      return '';
    }
  }

  final protected function buildPagingClause(
    AphrontDatabaseConnection $conn_r) {

    if ($this->beforeID) {
      return qsprintf(
        $conn_r,
        '%Q %Q %s',
        $this->getPagingColumn(),
        $this->getReversePaging() ? '<' : '>',
        $this->beforeID);
    } else if ($this->afterID) {
      return qsprintf(
        $conn_r,
        '%Q %Q %s',
        $this->getPagingColumn(),
        $this->getReversePaging() ? '>' : '<',
        $this->afterID);
    }

    return null;
  }

  final protected function buildOrderClause(AphrontDatabaseConnection $conn_r) {
    if ($this->beforeID) {
      return qsprintf(
        $conn_r,
        'ORDER BY %Q %Q',
        $this->getPagingColumn(),
        $this->getReversePaging() ? 'DESC' : 'ASC');
    } else {
      return qsprintf(
        $conn_r,
        'ORDER BY %Q %Q',
        $this->getPagingColumn(),
        $this->getReversePaging() ? 'ASC' : 'DESC');
    }
  }

  final protected function didLoadResults(array $results) {
    if ($this->beforeID) {
      $results = array_reverse($results, $preserve_keys = true);
    }
    return $results;
  }

  final public function executeWithCursorPager(AphrontCursorPagerView $pager) {
    $this->setLimit($pager->getPageSize() + 1);

    if ($pager->getAfterID()) {
      $this->setAfterID($pager->getAfterID());
    } else if ($pager->getBeforeID()) {
      $this->setBeforeID($pager->getBeforeID());
    }

    $results = $this->execute();

    $sliced_results = $pager->sliceResults($results);

    if ($pager->getBeforeID() || (count($results) > $pager->getPageSize())) {
      $pager->setNextPageID($this->getPagingValue(last($sliced_results)));
    }

    if ($pager->getAfterID() ||
       ($pager->getBeforeID() && (count($results) > $pager->getPageSize()))) {
      $pager->setPrevPageID($this->getPagingValue(head($sliced_results)));
    }

    return $sliced_results;
  }

}
