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

final class PhabricatorDaemonCombinedLogController
  extends PhabricatorDaemonController {


  public function processRequest() {
    $request = $this->getRequest();

    $pager = new AphrontPagerView();
    $pager->setOffset($request->getInt('page'));
    $pager->setPageSize(1000);

    $events = id(new PhabricatorDaemonLogEvent())->loadAllWhere(
      '1 = 1 ORDER BY id DESC LIMIT %d, %d',
      $pager->getOffset(),
      $pager->getPageSize() + 1);

    $events = $pager->sliceResults($events);
    $pager->setURI($request->getRequestURI(), 'page');

    $event_view = new PhabricatorDaemonLogEventsView();
    $event_view->setEvents($events);
    $event_view->setUser($request->getUser());
    $event_view->setCombinedLog(true);

    $log_panel = new AphrontPanelView();
    $log_panel->setHeader('Combined Daemon Logs');
    $log_panel->appendChild($event_view);
    $log_panel->appendChild($pager);

    $nav = $this->buildSideNavView();
    $nav->selectFilter('log/combined');
    $nav->appendChild($log_panel);

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => 'Combined Daemon Log',
      ));
  }

}
