<?php

final class PhabricatorQueryIterator extends PhutilBufferedIterator {

  private $query;
  private $pager;

  public function __construct(PhabricatorCursorPagedPolicyAwareQuery $query) {
    $this->query = $query;
  }

  protected function didRewind() {
    $this->pager = new AphrontCursorPagerView();
  }

  public function key() {
    return $this->current()->getID();
  }

  protected function loadPage() {
    if (!$this->pager) {
      return array();
    }

    $pager = clone $this->pager;
    $query = clone $this->query;

    $results = $query->executeWithCursorPager($pager);

    // If we got less than a full page of results, this was the last set of
    // results. Throw away the pager so we end iteration.
    if (count($results) < $pager->getPageSize()) {
      $this->pager = null;
    } else {
      $this->pager->setAfterID($pager->getNextPageID());
    }

    return $results;
  }

}
