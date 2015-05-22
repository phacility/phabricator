<?php

final class PhabricatorSortTableUIExample extends PhabricatorUIExample {

  public function getName() {
    return pht('Sortable Tables');
  }

  public function getDescription() {
    return pht('Using sortable tables.');
  }

  public function renderExample() {

    $rows = array(
      array(
        'make'    => 'Honda',
        'model'   => 'Civic',
        'year'    => 2004,
        'price'   => 3199,
        'color'   => 'Blue',
      ),
      array(
        'make'    => 'Ford',
        'model'   => 'Focus',
        'year'    => 2001,
        'price'   => 2549,
        'color'   => 'Red',
      ),
      array(
        'make'    => 'Toyota',
        'model'   => 'Camry',
        'year'    => 2009,
        'price'   => 4299,
        'color'   => 'Black',
      ),
      array(
        'make'    => 'NASA',
        'model'   => 'Shuttle',
        'year'    => 1998,
        'price'   => 1000000000,
        'color'   => 'White',
      ),
    );

    $request = $this->getRequest();

    $orders = array(
      'make',
      'model',
      'year',
      'price',
    );

    $sort = $request->getStr('sort');
    list($sort, $reverse) = AphrontTableView::parseSort($sort);
    if (!in_array($sort, $orders)) {
      $sort = 'make';
    }

    $rows = isort($rows, $sort);
    if ($reverse) {
      $rows = array_reverse($rows);
    }

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        pht('Make'),
        pht('Model'),
        pht('Year'),
        pht('Price'),
        pht('Color'),
      ));
    $table->setColumnClasses(
      array(
        '',
        'wide',
        'n',
        'n',
        '',
      ));
    $table->makeSortable(
      $request->getRequestURI(),
      'sort',
      $sort,
      $reverse,
      $orders);

    $panel = new PHUIObjectBoxView();
    $panel->setHeaderText(pht('Sortable Table of Vehicles'));
    $panel->appendChild($table);

    return $panel;
  }
}
