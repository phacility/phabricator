<?php

final class PhabricatorPagerUIExample extends PhabricatorUIExample {

  public function getName() {
    return 'Pager';
  }

  public function getDescription() {
    return hsprintf(
      'Use <tt>AphrontPagerView</tt> to create a control which allows '.
      'users to paginate through large amounts of content.');
  }

  public function renderExample() {

    $request = $this->getRequest();

    $offset = (int)$request->getInt('offset');
    $page_size = 20;
    $item_count = 173;

    $rows = array();
    for ($ii = $offset; $ii < min($item_count, $offset + $page_size); $ii++) {
      $rows[] = array(
        'Item #'.($ii + 1),
      );
    }

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        'Item',
      ));
    $panel = new PHUIObjectBoxView();
    $panel->setHeaderText(pht('Example'));
    $panel->appendChild($table);

    $panel->appendChild(hsprintf(
      '<p class="phabricator-ui-example-note">'.
        'Use <tt>AphrontPagerView</tt> to render a pager element.'.
      '</p>'));

    $pager = new AphrontPagerView();
    $pager->setPageSize($page_size);
    $pager->setOffset($offset);
    $pager->setCount($item_count);
    $pager->setURI($request->getRequestURI(), 'offset');
    $panel->appendChild($pager);

    $panel->appendChild(hsprintf(
      '<p class="phabricator-ui-example-note">'.
        'You can show more or fewer pages of surrounding context.'.
      '</p>'));

    $many_pages_pager = new AphrontPagerView();
    $many_pages_pager->setPageSize($page_size);
    $many_pages_pager->setOffset($offset);
    $many_pages_pager->setCount($item_count);
    $many_pages_pager->setURI($request->getRequestURI(), 'offset');
    $many_pages_pager->setSurroundingPages(7);
    $panel->appendChild($many_pages_pager);

    $panel->appendChild(hsprintf(
      '<p class="phabricator-ui-example-note">'.
        'When it is prohibitively expensive or complex to attain a complete '.
        'count of the items, you can select one extra item and set '.
        '<tt>hasMorePages(true)</tt> if it exists, creating an inexact pager.'.
      '</p>'));

    $inexact_pager = new AphrontPagerView();
    $inexact_pager->setPageSize($page_size);
    $inexact_pager->setOffset($offset);
    $inexact_pager->setHasMorePages($offset < ($item_count - $page_size));
    $inexact_pager->setURI($request->getRequestURI(), 'offset');
    $panel->appendChild($inexact_pager);

    return $panel;
  }
}
