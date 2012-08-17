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

final class PhabricatorMailingListsListController
  extends PhabricatorController {

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();
    $offset = $request->getInt('offset', 0);

    $pager = new AphrontPagerView();
    $pager->setPageSize(250);
    $pager->setOffset($offset);
    $pager->setURI($request->getRequestURI(), 'offset');

    $list = new PhabricatorMetaMTAMailingList();
    $conn_r = $list->establishConnection('r');
    $data = queryfx_all(
      $conn_r,
      'SELECT * FROM %T
        ORDER BY name ASC
        LIMIT %d, %d',
        $list->getTableName(),
        $pager->getOffset(), $pager->getPageSize() + 1);
    $data = $pager->sliceResults($data);

    $lists = $list->loadAllFromArray($data);

    $rows = array();
    foreach ($lists as $list) {
      $rows[] = array(
        phutil_escape_html($list->getName()),
        phutil_escape_html($list->getEmail()),
        phutil_render_tag(
          'a',
          array(
            'class' => 'button grey small',
            'href'  => $this->getApplicationURI('/edit/'.$list->getID().'/'),
          ),
          'Edit'),
      );
    }

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        'Name',
        'Email',
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
    $panel->setHeader('Mailing Lists');
    $panel->setCreateButton('Add New List', $this->getApplicationURI('/edit/'));
    $panel->appendChild($pager);

    return $this->buildApplicationPage(
      $panel,
      array(
        'title' => 'Mailing Lists',
      ));
  }
}
