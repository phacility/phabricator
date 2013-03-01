<?php

final class AphrontCursorPagerView extends AphrontView {

  private $afterID;
  private $beforeID;

  private $pageSize = 100;

  private $nextPageID;
  private $prevPageID;
  private $moreResults;

  private $uri;

  final public function setPageSize($page_size) {
    $this->pageSize = max(1, $page_size);
    return $this;
  }

  final public function getPageSize() {
    return $this->pageSize;
  }

  final public function setURI(PhutilURI $uri) {
    $this->uri = $uri;
    return $this;
  }

  final public function readFromRequest(AphrontRequest $request) {
    $this->uri = $request->getRequestURI();
    $this->afterID = $request->getStr('after');
    $this->beforeID = $request->getStr('before');
    return $this;
  }

  final public function setAfterID($after_id) {
    $this->afterID = $after_id;
    return $this;
  }

  final public function getAfterID() {
    return $this->afterID;
  }

  final public function setBeforeID($before_id) {
    $this->beforeID = $before_id;
    return $this;
  }

  final public function getBeforeID() {
    return $this->beforeID;
  }

  final public function setNextPageID($next_page_id) {
    $this->nextPageID = $next_page_id;
    return $this;
  }

  final public function getNextPageID() {
    return $this->nextPageID;
  }

  final public function setPrevPageID($prev_page_id) {
    $this->prevPageID = $prev_page_id;
    return $this;
  }

  final public function getPrevPageID() {
    return $this->prevPageID;
  }

  final public function sliceResults(array $results) {
    if (count($results) > $this->getPageSize()) {
      $offset = ($this->beforeID ? count($results) - $this->getPageSize() : 0);
      $results = array_slice($results, $offset, $this->getPageSize(), true);
      $this->moreResults = true;
    }
    return $results;
  }

  public function render() {
    if (!$this->uri) {
      throw new Exception(
        pht("You must call setURI() before you can call render()."));
    }

    $links = array();

    if ($this->afterID || ($this->beforeID && $this->moreResults)) {
      $links[] = phutil_tag(
        'a',
        array(
          'href' => $this->uri
            ->alter('before', null)
            ->alter('after', null),
        ),
        "\xC2\xAB ". pht("First"));
    }

    if ($this->prevPageID) {
      $links[] = phutil_tag(
        'a',
        array(
          'href' => $this->uri
            ->alter('after', null)
            ->alter('before', $this->prevPageID),
        ),
        "\xE2\x80\xB9 " . pht("Prev"));
    }

    if ($this->nextPageID) {
      $links[] = phutil_tag(
        'a',
        array(
          'href' => $this->uri
            ->alter('after', $this->nextPageID)
            ->alter('before', null),
        ),
        "Next \xE2\x80\xBA");
    }

    return phutil_tag(
      'div',
      array('class' => 'aphront-pager-view'),
      $links);
  }

}
