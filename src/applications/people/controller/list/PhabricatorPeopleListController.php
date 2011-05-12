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
    $request = $this->getRequest();
    $is_admin = $request->getUser()->getIsAdmin();

    $user = new PhabricatorUser();

    $count = queryfx_one(
      $user->establishConnection('r'),
      'SELECT COUNT(*) N FROM %T',
      $user->getTableName());
    $count = idx($count, 'N', 0);

    $pager = new AphrontPagerView();
    $pager->setOffset($request->getInt('page', 0));
    $pager->setCount($count);
    $pager->setURI($request->getRequestURI(), 'page');

    $users = id(new PhabricatorUser())->loadAllWhere(
      '1 = 1 ORDER BY id DESC LIMIT %d, %d',
      $pager->getOffset(),
      $pager->getPageSize());

    $rows = array();
    foreach ($users as $user) {
      $cols = array();
      $cols[] = date('M jS, Y', $user->getDateCreated());
      $cols[] = date('g:i:s A', $user->getDateCreated());
      $cols[] = phutil_render_tag(
        'a',
        array(
          'href' => '/p/'.$user->getUsername().'/',
        ),
        phutil_escape_html($user->getUserName()));
      $cols[] = phutil_escape_html($user->getRealName());

      if ($is_admin) {
        $status = '';
        if ($user->getIsDisabled()) {
          $status = 'Disabled';
        } else if ($user->getIsAdmin()) {
          $status = 'Admin';
        } else {
          $status = '-';
        }
        $cols[] = $status;
        $cols[] = phutil_render_tag(
          'a',
          array(
            'class' => 'button grey small',
            'href'  => '/people/edit/'.$user->getID().'/',
          ),
          'Administrate User');
      }

      $rows[] = $cols;
    }

    $table = new AphrontTableView($rows);
    if ($is_admin) {
      $table->setHeaders(
        array(
          'Join Date',
          'Time',
          'Username',
          'Real Name',
          'Status',
          '',
        ));
      $table->setColumnClasses(
        array(
          null,
          'right',
          'pri',
          'wide',
          null,
          'action',
        ));
    } else {
      $table->setHeaders(
        array(
          'Join Date',
          'Time',
          'Username',
          'Real Name',
        ));
      $table->setColumnClasses(
        array(
          null,
          'right',
          'pri',
          'wide',
        ));
    }

    $panel = new AphrontPanelView();
    $panel->setHeader('People ('.number_format($count).')');
    $panel->appendChild($table);
    $panel->appendChild($pager);

    if ($is_admin) {
      $panel->addButton(
        phutil_render_tag(
          'a',
          array(
            'href' => '/people/edit/',
            'class' => 'button green',
          ),
          'Create New Account'));
    }

    return $this->buildStandardPageResponse($panel, array(
      'title' => 'People',
      'tab'   => 'people',
      ));
  }
}
