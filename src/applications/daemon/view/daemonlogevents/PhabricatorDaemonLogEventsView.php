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

final class PhabricatorDaemonLogEventsView extends AphrontView {

  private $events;
  private $combinedLog;

  public function setEvents(array $events) {
    $this->events = $events;
  }

  public function setCombinedLog($is_combined) {
    $this->combinedLog = $is_combined;
  }

  public function render() {
    $rows = array();

    foreach ($this->events as $event) {
      $row = array(
        phutil_escape_html($event->getLogType()),
        date('M j, Y', $event->getEpoch()),
        date('g:i:s A', $event->getEpoch()),
        str_replace("\n", '<br />', phutil_escape_html($event->getMessage())),
      );

      if ($this->combinedLog) {
        array_unshift(
          $row,
          phutil_render_tag(
            'a',
            array(
              'href' => '/daemon/log/'.$event->getLogID().'/',
            ),
            phutil_escape_html('Daemon '.$event->getLogID())));
      }

      $rows[] = $row;
    }

    $classes = array(
      '',
      '',
      'right',
      'wide wrap',
    );

    $headers = array(
      'Type',
      'Date',
      'Time',
      'Message',
    );

    if ($this->combinedLog) {
      array_unshift($classes, 'pri');
      array_unshift($headers, 'Daemon');
    }

    $log_table = new AphrontTableView($rows);
    $log_table->setHeaders($headers);
    $log_table->setColumnClasses($classes);

    return $log_table->render();
  }

}
