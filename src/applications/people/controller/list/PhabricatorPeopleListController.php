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

class PhabricatorPeopleListController extends PhabricatorPeopleController {

  public function processRequest() {
    $users = id(new PhabricatorUser())->loadAllWhere(
      '1 = 1 ORDER BY id DESC LIMIT 100');

    $rows = array();
    foreach ($users as $user) {
      $rows[] = array(
        $user->getPHID(),
        $user->getUserName(),
        $user->getRealName(),
        phutil_render_tag(
          'a',
          array(
            'class' => 'button grey small',
            'href'  => '/p/'.$user->getUsername().'/',
          ),
          'View Profile'),
        phutil_render_tag(
          'a',
          array(
            'class' => 'button grey small',
            'href'  => '/people/edit/'.$user->getUsername().'/',
          ),
          'Edit'),
      );
    }

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        'PHID',
        'Username',
        'Real Name',
        '',
        '',
      ));
    $table->setColumnClasses(
      array(
        null,
        null,
        'wide',
        'action',
        'action',
      ));

    $panel = new AphrontPanelView();
    $panel->appendChild($table);
    $panel->setHeader('People');
    $panel->setCreateButton('Create New User', '/people/edit/');

    return $this->buildStandardPageResponse($panel, array(
      'title' => 'People',
      'tab'   => 'people',
      ));
  }
}
