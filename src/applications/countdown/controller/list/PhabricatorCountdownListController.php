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

class PhabricatorCountdownListController
  extends PhabricatorCountdownController {

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    $pager = new AphrontPagerView();
    $pager->setOffset($request->getInt('page'));
    $pager->setURI($request->getRequestURI(), 'page');

    $timers = id(new PhabricatorTimer())->loadAllWhere(
      '1 = 1 ORDER BY id DESC LIMIT %d, %d',
      $pager->getOffset(),
      $pager->getPageSize() + 1);

    $timers = $pager->sliceResults($timers);

    $rows = array();
    foreach ($timers as $timer) {

      $control_buttons = array();
      $control_buttons[] = phutil_render_tag(
        'a',
        array(
          'class' => 'small button grey',
          'href' => '/countdown/'.$timer->getID().'/',
        ),
        'View');

      if ($user->getIsAdmin() ||
          ($user->getPHID() == $timer->getAuthorPHID())) {

        $control_buttons[] = phutil_render_tag(
          'a',
          array(
            'class' => 'small button grey',
            'href' => '/countdown/edit/'.$timer->getID().'/'
          ),
          'Edit');

        $control_buttons[] = javelin_render_tag(
          'a',
          array(
            'class' => 'small button grey',
            'href' => '/countdown/delete/'.$timer->getID().'/',
            'sigil' => 'workflow'
          ),
          'Delete');

      }

      $rows[] = array(
        phutil_escape_html($timer->getID()),
        phutil_escape_html($timer->getTitle()),
        phabricator_format_timestamp($timer->getDatepoint()),
        implode('', $control_buttons)
      );
    }

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        'ID',
        'Title',
        'End Date',
        'Action'
      ));

    $table->setColumnClasses(
      array(
        null,
        null,
        null,
        'action'
      ));

    $panel = id(new AphrontPanelView())
      ->appendChild($table)
      ->setHeader('Timers')
      ->setCreateButton('Create Timer', '/countdown/edit/')
      ->appendChild($pager);

    return $this->buildStandardPageResponse($panel,
      array(
        'title' => 'Countdown',
        'tab' => 'countdown',
      ));
  }
}
