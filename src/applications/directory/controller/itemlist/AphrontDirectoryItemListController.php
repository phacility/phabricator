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

class AphrontDirectoryItemListController extends AphrontDirectoryController {

  public function processRequest() {
    $items = id(new AphrontDirectoryItem())->loadAll();
    $items = msort($items, 'getSortKey');

    $categories = id(new AphrontDirectoryCategory())->loadAll();
    $category_names = mpull($categories, 'getName', 'getID');

    $rows = array();
    foreach ($items as $item) {
      $rows[] = array(
        $item->getID(),
        phutil_escape_html(idx($category_names, $item->getCategoryID())),
        phutil_render_tag(
          'a',
          array(
            'href' => '/directory/item/edit/'.$item->getID().'/',
          ),
          phutil_escape_html($item->getName())),
        phutil_render_tag(
          'a',
          array(
            'href' => '/directory/item/delete/'.$item->getID().'/',
            'class' => 'button grey small',
          ),
          'Delete'),
      );
    }


    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        'ID',
        'Category',
        'Name',
        '',
      ));
    $table->setColumnClasses(
      array(
        null,
        null,
        'wide',
        'action',
      ));

    $panel = new AphrontPanelView();
    $panel->appendChild($table);
    $panel->setHeader('Directory Items');
    $panel->setCreateButton('New Item', '/directory/item/edit/');

    return $this->buildStandardPageResponse($panel, array(
      'title' => 'Directory Items',
      'tab'   => 'items',
      ));
  }

}
