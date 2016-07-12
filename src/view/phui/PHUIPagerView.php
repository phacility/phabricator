<?php

final class PHUIPagerView extends AphrontView {

  private $offset;
  private $pageSize = 100;

  private $count;
  private $hasMorePages;

  private $uri;
  private $pagingParameter;
  private $surroundingPages = 2;
  private $enableKeyboardShortcuts;

  public function setPageSize($page_size) {
    $this->pageSize = max(1, $page_size);
    return $this;
  }

  public function setOffset($offset) {
    $this->offset = max(0, $offset);
    return $this;
  }

  public function getOffset() {
    return $this->offset;
  }

  public function getPageSize() {
    return $this->pageSize;
  }

  public function setCount($count) {
    $this->count = $count;
    return $this;
  }

  public function setHasMorePages($has_more) {
    $this->hasMorePages = $has_more;
    return $this;
  }

  public function setURI(PhutilURI $uri, $paging_parameter) {
    $this->uri = $uri;
    $this->pagingParameter = $paging_parameter;
    return $this;
  }

  public function readFromRequest(AphrontRequest $request) {
    $this->uri = $request->getRequestURI();
    $this->pagingParameter = 'offset';
    $this->offset = $request->getInt($this->pagingParameter);
    return $this;
  }

  public function willShowPagingControls() {
    return $this->hasMorePages;
  }

  public function getHasMorePages() {
    return $this->hasMorePages;
  }

  public function setSurroundingPages($pages) {
    $this->surroundingPages = max(0, $pages);
    return $this;
  }

  private function computeCount() {
    if ($this->count !== null) {
      return $this->count;
    }
    return $this->getOffset()
      + $this->getPageSize()
      + ($this->hasMorePages ? 1 : 0);
  }

  private function isExactCountKnown() {
    return $this->count !== null;
  }

  /**
   * A common paging strategy is to select one extra record and use that to
   * indicate that there's an additional page (this doesn't give you a
   * complete page count but is often faster than counting the total number
   * of items). This method will take a result array, slice it down to the
   * page size if necessary, and call setHasMorePages() if there are more than
   * one page of results.
   *
   *    $results = queryfx_all(
   *      $conn,
   *      'SELECT ... LIMIT %d, %d',
   *      $pager->getOffset(),
   *      $pager->getPageSize() + 1);
   *    $results = $pager->sliceResults($results);
   *
   * @param   list  Result array.
   * @return  list  One page of results.
   */
  public function sliceResults(array $results) {
    if (count($results) > $this->getPageSize()) {
      $results = array_slice($results, 0, $this->getPageSize(), true);
      $this->setHasMorePages(true);
    }
    return $results;
  }

  public function setEnableKeyboardShortcuts($enable) {
    $this->enableKeyboardShortcuts = $enable;
    return $this;
  }

  public function render() {
    if (!$this->uri) {
      throw new PhutilInvalidStateException('setURI');
    }

    require_celerity_resource('phui-pager-css');

    $page = (int)floor($this->getOffset() / $this->getPageSize());
    $last = ((int)ceil($this->computeCount() / $this->getPageSize())) - 1;
    $near = $this->surroundingPages;

    $min = $page - $near;
    $max = $page + $near;

    // Limit the window size to no larger than the number of available pages.
    if ($max - $min > $last) {
      $max = $min + $last;
      if ($max == $min) {
        return phutil_tag('div', array('class' => 'phui-pager-view'), '');
      }
    }

    // Slide the window so it is entirely over displayable pages.
    if ($min < 0) {
      $max += 0 - $min;
      $min += 0 - $min;
    }

    if ($max > $last) {
      $min -= $max - $last;
      $max -= $max - $last;
    }


    // Build up a list of <index, label, css-class> tuples which describe the
    // links we'll display, then render them all at once.

    $links = array();

    $prev_index = null;
    $next_index = null;

    if ($min > 0) {
      $links[] = array(0, pht('First'), null);
    }

    if ($page > 0) {
      $links[] = array($page - 1, pht('Prev'), null);
      $prev_index = $page - 1;
    }

    for ($ii = $min; $ii <= $max; $ii++) {
      $links[] = array($ii, $ii + 1, ($ii == $page) ? 'current' : null);
    }

    if ($page < $last && $last > 0) {
      $links[] = array($page + 1, pht('Next'), null);
      $next_index = $page + 1;
    }

    if ($max < ($last - 1)) {
      $links[] = array($last, pht('Last'), null);
    }

    $base_uri = $this->uri;
    $parameter = $this->pagingParameter;

    if ($this->enableKeyboardShortcuts) {
      $pager_links = array();
      $pager_index = array(
        'prev' => $prev_index,
        'next' => $next_index,
      );
      foreach ($pager_index as $key => $index) {
        if ($index !== null) {
          $display_index = $this->getDisplayIndex($index);
          $pager_links[$key] = (string)$base_uri->alter(
            $parameter,
            $display_index);
        }
      }
      Javelin::initBehavior('phabricator-keyboard-pager', $pager_links);
    }

    // Convert tuples into rendered nodes.
    $rendered_links = array();
    foreach ($links as $link) {
      list($index, $label, $class) = $link;
      $display_index = $this->getDisplayIndex($index);
      $link = $base_uri->alter($parameter, $display_index);
      $rendered_links[] = id(new PHUIButtonView())
        ->setTag('a')
        ->setHref($link)
        ->setColor(PHUIButtonView::SIMPLE)
        ->addClass('mml')
        ->addClass($class)
        ->setText($label);
    }

    return phutil_tag(
      'div',
      array(
        'class' => 'phui-pager-view',
      ),
      $rendered_links);
  }

  private function getDisplayIndex($page_index) {
    $page_size = $this->getPageSize();
    // Use a 1-based sequence for display so that the number in the URI is
    // the same as the page number you're on.
    if ($page_index == 0) {
      // No need for the first page to say page=1.
      $display_index = null;
    } else {
      $display_index = $page_index * $page_size;
    }
    return $display_index;
  }

}
