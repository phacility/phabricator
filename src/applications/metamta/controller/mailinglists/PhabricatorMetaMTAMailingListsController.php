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

class PhabricatorMetaMTAMailingListsController
  extends PhabricatorMetaMTAController {

  public function processRequest() {
    $lists = id(new PhabricatorMetaMTAMailingList())->loadAllWhere(
      '1 = 1 ORDER BY id DESC LIMIT 100');

    $rows = array();
    foreach ($lists as $list) {
      $rows[] = array(
        phutil_escape_html($list->getPHID()),
        phutil_escape_html($list->getEmail()),
        phutil_escape_html($list->getName()),
        phutil_render_tag(
          'a',
          array(
            'class' => 'button grey small',
            'href'  => '/mail/lists/edit/'.$list->getID().'/',
          ),
          'Edit'),
      );
    }

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        'PHID',
        'Email',
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
    $panel->setHeader('Mailing Lists');
    $panel->setCreateButton('Add New List', '/mail/lists/edit/');

    return $this->buildStandardPageResponse(
      $panel,
      array(
        'title' => 'Mailing Lists',
        'tab'   => 'lists',
      ));
  }
}
