<?php

/*
 * Copyright 2012 Facebook, Inc.
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

final class PhabricatorSortTableExample extends PhabricatorUIExample {

  public function getName() {
    return 'Sortable Tables';
  }

  public function getDescription() {
    return 'Using sortable tables.';
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
        'Make',
        'Model',
        'Year',
        'Price',
        'Color',
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

    $panel = new AphrontPanelView();
    $panel->setHeader('Sortable Table of Vehicles');
    $panel->appendChild($table);

    return $panel;
  }
}
