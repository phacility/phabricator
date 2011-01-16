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

class AphrontDirectoryCategoryListController
  extends AphrontDirectoryController {

  public function processRequest() {
    $categories = id(new AphrontDirectoryCategory())->loadAll();
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
        phutil_render_tag(
          'a',
          array(
            'href' => '/directory/category/delete/'.$category->getID().'/',
            'class' => 'button grey small',
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

    return $this->buildStandardPageResponse($panel, array(
      'title' => 'Directory Category List',
      'tab'   => 'categories',
      ));
  }

}
