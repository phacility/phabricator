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

final class DrydockLogController extends DrydockController {

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $nav = $this->buildSideNav('log');

    $query = new DrydockLogQuery();

    $resource_ids = $request->getStrList('resource');
    if ($resource_ids) {
      $query->withResourceIDs($resource_ids);
    }

    $lease_ids = $request->getStrList('lease');
    if ($lease_ids) {
      $query->withLeaseIDs($lease_ids);
    }

    $pager = new AphrontPagerView();
    $pager->setPageSize(500);
    $pager->setOffset($request->getInt('offset'));
    $pager->setURI($request->getRequestURI(), 'offset');

    $logs = $query->executeWithOffsetPager($pager);

    $rows = array();
    foreach ($logs as $log) {
      $rows[] = array(
        $log->getResourceID(),
        $log->getLeaseID(),
        phutil_escape_html($log->getMessage()),
        phabricator_datetime($log->getEpoch(), $user),
      );
    }

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        'Resource',
        'Lease',
        'Message',
        'Date',
      ));
    $table->setColumnClasses(
      array(
        '',
        '',
        'wide',
        '',
      ));

    $panel = new AphrontPanelView();
    $panel->setHeader('Drydock Logs');
    $panel->appendChild($table);
    $panel->appendChild($pager);

    $nav->appendChild($panel);

    return $this->buildStandardPageResponse(
      $nav,
      array(
        'title' => 'Logs',
      ));

  }

}
