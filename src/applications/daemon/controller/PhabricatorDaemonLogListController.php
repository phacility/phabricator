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

final class PhabricatorDaemonLogListController
  extends PhabricatorDaemonController {

  private $running;

  public function willProcessRequest(array $data) {
    $this->running = !empty($data['running']);
  }

  public function processRequest() {
    $request = $this->getRequest();

    $pager = new AphrontPagerView();
    $pager->setOffset($request->getInt('page'));

    $clause = '1 = 1';
    if ($this->running) {
      $clause = "`status` != 'exit'";
    }

    $logs = id(new PhabricatorDaemonLog())->loadAllWhere(
      '%Q ORDER BY id DESC LIMIT %d, %d',
      $clause,
      $pager->getOffset(),
      $pager->getPageSize() + 1);

    $logs = $pager->sliceResults($logs);
    $pager->setURI($request->getRequestURI(), 'page');

    $daemon_table = new PhabricatorDaemonLogListView();
    $daemon_table->setUser($request->getUser());
    $daemon_table->setDaemonLogs($logs);

    $daemon_panel = new AphrontPanelView();
    $daemon_panel->setHeader('Launched Daemons');
    $daemon_panel->appendChild($daemon_table);
    $daemon_panel->appendChild($pager);

    $nav = $this->buildSideNavView();
    $nav->selectFilter($this->running ? 'log/running' : 'log');
    $nav->appendChild($daemon_panel);

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => $this->running ? 'Running Daemons' : 'All Daemons',
      ));
  }

}
