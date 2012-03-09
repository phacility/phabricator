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

/**
 * @group conduit
 */
final class PhabricatorConduitLogController
  extends PhabricatorConduitController {

  public function processRequest() {
    $request = $this->getRequest();

    $conn_table = new PhabricatorConduitConnectionLog();
    $call_table = new PhabricatorConduitMethodCallLog();

    $conn_r = $call_table->establishConnection('r');

    $pager = new AphrontPagerView();
    $pager->setOffset($request->getInt('page'));
    $calls = $call_table->loadAllWhere(
      '1 = 1 ORDER BY id DESC LIMIT %d, %d',
      $pager->getOffset(),
      $pager->getPageSize() + 1);
    $calls = $pager->sliceResults($calls);
    $pager->setURI(new PhutilURI('/conduit/log/'), 'page');
    $pager->setEnableKeyboardShortcuts(true);

    $min = $pager->getOffset() + 1;
    $max = ($min + count($calls) - 1);

    $conn_ids = array_filter(mpull($calls, 'getConnectionID'));
    $conns = array();
    if ($conn_ids) {
      $conns = $conn_table->loadAllWhere(
        'id IN (%Ld)',
        $conn_ids);
    }

    $table = $this->renderCallTable($calls, $conns);
    $panel = new AphrontPanelView();
    $panel->setHeader('Conduit Method Calls ('.$min.'-'.$max.')');
    $panel->appendChild($table);
    $panel->appendChild($pager);

    $this->setFilter('log');

    return $this->buildStandardPageResponse(
      $panel,
      array(
        'title' => 'Conduit Logs',
      ));
  }

  private function renderCallTable(array $calls, array $conns) {
    $user = $this->getRequest()->getUser();

    $rows = array();
    foreach ($calls as $call) {
      $conn = idx($conns, $call->getConnectionID());
      if (!$conn) {
        // If there's no connection, use an empty object.
        $conn = new PhabricatorConduitConnectionLog();
      }
      $rows[] = array(
        $call->getConnectionID(),
        phutil_escape_html($conn->getUserName()),
        phutil_escape_html($call->getMethod()),
        phutil_escape_html($call->getError()),
        number_format($call->getDuration()).' us',
        phabricator_datetime($call->getDateCreated(), $user),
      );
    }
    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        'Connection',
        'User',
        'Method',
        'Error',
        'Duration',
        'Date',
      ));
    $table->setColumnClasses(
      array(
        '',
        '',
        'wide',
        '',
        'n',
        'right',
      ));
    return $table;
  }

}
