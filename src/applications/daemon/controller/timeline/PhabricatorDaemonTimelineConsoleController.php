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

final class PhabricatorDaemonTimelineConsoleController
  extends PhabricatorDaemonController {

  public function processRequest() {

    $timeline_table = new PhabricatorTimelineEvent('NULL');

    $events = queryfx_all(
      $timeline_table->establishConnection('r'),
      'SELECT id, type FROM %T ORDER BY id DESC LIMIT 100',
      $timeline_table->getTableName());

    $rows = array();
    foreach ($events as $event) {
      $rows[] = array(
        phutil_render_tag(
          'a',
          array(
            'href' => '/daemon/timeline/'.$event['id'].'/',
          ),
          $event['id']),
        phutil_escape_html($event['type']),
      );
    }

    $event_table = new AphrontTableView($rows);
    $event_table->setHeaders(
      array(
        'ID',
        'Type',
      ));
    $event_table->setColumnClasses(
      array(
        null,
        'wide',
      ));

    $event_panel = new AphrontPanelView();
    $event_panel->setHeader('Timeline Events');
    $event_panel->appendChild($event_table);

    return $this->buildStandardPageResponse(
      array(
        $event_panel,
      ),
      array(
        'title' => 'Timeline',
        'tab'   => 'timeline',
      ));
  }

}
