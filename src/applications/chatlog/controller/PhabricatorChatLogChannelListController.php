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

final class PhabricatorChatLogChannelListController
  extends PhabricatorChatLogController {

  public function processRequest() {

    $table = new PhabricatorChatLogEvent();

    $channels = queryfx_all(
      $table->establishConnection('r'),
      'SELECT DISTINCT channel FROM %T',
      $table->getTableName());

    $rows = array();
    foreach ($channels as $channel) {
      $name = $channel['channel'];
      $rows[] = array(
        phutil_render_tag(
          'a',
          array(
            'href' => '/chatlog/channel/'.phutil_escape_uri($name).'/',
          ),
          phutil_escape_html($name)));
    }

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        'Channel',
      ));
    $table->setColumnClasses(
      array(
        'pri wide',
      ));

    $panel = new AphrontPanelView();
    $panel->appendChild($table);

    return $this->buildStandardPageResponse(
      $panel,
      array(
        'title' => 'Channel List',
      ));
  }
}
