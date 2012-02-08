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

final class PhabricatorDirectoryEditController
  extends PhabricatorDirectoryController {

  public function processRequest() {
    $nav = $this->buildNav();
    $nav->selectFilter('directory/edit', 'directory/edit');

    $nav->appendChild($this->buildCategoryList());
    $nav->appendChild($this->buildItemList());

    return $this->buildStandardPageResponse(
      $nav,
      array(
        'title' => 'Edit Applications',
      ));
  }

  private function buildCategoryList() {
    $categories = id(new PhabricatorDirectoryCategory())->loadAll();
    $categories = msort($categories, 'getSequence');

    $rows = array();
    foreach ($categories as $category) {
      $rows[] = array(
        $category->getID(),
        phutil_render_tag(
          'a',
          array(
            'href' => '/directory/category/edit/'.$category->getID().'/',
          ),
          phutil_escape_html($category->getName())),
        javelin_render_tag(
          'a',
          array(
            'href' => '/directory/category/delete/'.$category->getID().'/',
            'class' => 'button grey small',
            'sigil' => 'workflow',
          ),
          'Delete'),
      );
    }

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        'ID',
        'Name',
        '',
      ));
    $table->setColumnClasses(
      array(
        null,
        'wide',
        'action',
      ));

    $panel = new AphrontPanelView();
    $panel->appendChild($table);
    $panel->setHeader('Directory Categories');
    $panel->setCreateButton('New Category', '/directory/category/edit/');

    return $panel;
  }

  private function buildItemList() {
    $items = id(new PhabricatorDirectoryItem())->loadAll();
    $items = msort($items, 'getSortKey');

    $categories = id(new PhabricatorDirectoryCategory())->loadAll();
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
        javelin_render_tag(
          'a',
          array(
            'href' => '/directory/item/delete/'.$item->getID().'/',
            'class' => 'button grey small',
            'sigil' => 'workflow',
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

    return $panel;
  }
}
