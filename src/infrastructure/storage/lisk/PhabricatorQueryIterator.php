<?php

final class PhabricatorQueryIterator extends PhutilBufferedIterator {

  private $query;
  private $pager;

  public function __construct(PhabricatorCursorPagedPolicyAwareQuery $query) {
    $this->query = $query;
  }

  protected function didRewind() {
    $pager = new AphrontCursorPagerView();

    $page_size = $this->getPageSize();
    $pager->setPageSize($page_size);

    $this->pager = $pager;
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

    $query->setDisableOverheating(true);

    $results = $query->executeWithCursorPager($pager);

    // If we got less than a full page of results, this was the last set of
    // results. Throw away the pager so we end iteration.
    if (!$pager->getHasMoreResults()) {
      $this->pager = null;
    } else {
      $this->pager->setAfterID($pager->getNextPageID());
    }

    return $results;
  }

}
