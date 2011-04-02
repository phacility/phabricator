<?php

/*
 * Copyright 2011 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

final class AphrontPagerView extends AphrontView {

  private $offset;
  private $pageSize = 100;

  private $count;
  private $hasMorePages;

  private $uri;
  private $pagingParameter;
  private $surroundingPages = 2;

  final public function setPageSize($page_size) {
    $this->pageSize = max(1, $page_size);
    return $this;
  }

  final public function setOffset($offset) {
    $this->offset = max(0, $offset);
    return $this;
  }

  final public function getOffset() {
    return $this->offset;
  }

  final public function getPageSize() {
    return $this->pageSize;
  }

  final public function setCount($count) {
    $this->count = $count;
    return $this;
  }

  final public function setHasMorePages($has_more) {
    $this->hasMorePages = $has_more;
    return $this;
  }

  final public function setURI(PhutilURI $uri, $paging_parameter) {
    $this->uri = $uri;
    $this->pagingParameter = $paging_parameter;
    return $this;
  }

  final public function setSurroundingPages($pages) {
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

  public function render() {
    if (!$this->uri) {
      throw new Exception(
        "You must call setURI() before you can call render().");
    }

    require_celerity_resource('aphront-pager-view-css');

    $page = (int)floor($this->getOffset() / $this->getPageSize());
    $last = ((int)ceil($this->computeCount() / $this->getPageSize())) - 1;
    $near = $this->surroundingPages;

    $min = $page - $near;
    $max = $page + $near;

    // Limit the window size to no larger than the number of available pages.
    if ($max - $min > $last) {
      $max = $min + $last;
      if ($max == $min) {
        return '<div class="aphront-pager-view"></div>';
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

    if ($min > 0) {
      $links[] = array(0, 'First', null);
    }

    if ($page > 0) {
      $links[] = array($page - 1, 'Prev', null);
    }

    for ($ii = $min; $ii <= $max; $ii++) {
      $links[] = array($ii, $ii + 1, ($ii == $page) ? 'current' : null);
    }

    if ($page < $last && $last > 0) {
      $links[] = array($page + 1, 'Next', null);
    }

    if ($max < ($last - 1)) {
      $links[] = array($last, 'Last', null);
    }

    $base_uri = $this->uri;
    $parameter = $this->pagingParameter;
    $page_size = $this->getPageSize();

    // Convert tuples into rendered nodes.
    $rendered_links = array();
    foreach ($links as $link) {
      list($index, $label, $class) = $link;
      // Use a 1-based sequence for display so that the number in the URI is
      // the same as the page number you're on.
      if ($index == 0) {
        // No need for the first page to say page=1.
        $display_index = null;
      } else {
        $display_index = $index * $page_size;
      }
      $link = $base_uri->alter($parameter, $display_index);
      $rendered_links[] = phutil_render_tag(
        'a',
        array(
          'href' => $link,
          'class' => $class,
        ),
        $label);
    }

    return
      '<div class="aphront-pager-view">'.
        implode('', $rendered_links).
      '</div>';
  }

}
