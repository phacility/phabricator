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

class PhabricatorDaemonLogViewController extends PhabricatorDaemonController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {

    $log = id(new PhabricatorDaemonLog())->load($this->id);
    if (!$log) {
      return new Aphront404Response();
    }

    $events = id(new PhabricatorDaemonLogEvent())->loadAllWhere(
      'logID = %d ORDER BY id DESC LIMIT 200',
      $log->getID());

    $content = array();


    $rows = array();
    foreach ($events as $event) {
      $rows[] = array(
        phutil_escape_html($event->getLogType()),
        date('M j, Y', $event->getEpoch()),
        date('g:i:s A', $event->getEpoch()),
        str_replace("\n", '<br />', phutil_escape_html($event->getMessage())),
      );
    }

    $log_table = new AphrontTableView($rows);
    $log_table->setHeaders(
      array(
        'Type',
        'Date',
        'Time',
        'Message',
      ));
    $log_table->setColumnClasses(
      array(
        '',
        '',
        'right',
        'wide wrap',
      ));

    $log_panel = new AphrontPanelView();
    $log_panel->setHeader('Daemon Logs');
    $log_panel->appendChild($log_table);

    $content[] = $log_panel;



    return $this->buildStandardPageResponse(
      $content,
      array(
        'title' => 'Log',
      ));
  }

}
