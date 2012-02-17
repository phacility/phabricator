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

final class PhabricatorChatLogChannelLogController
  extends PhabricatorChatLogController {

  private $channel;

  public function willProcessRequest(array $data) {
    $this->channel = $data['channel'];
  }

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    $query = new PhabricatorChatLogQuery();
    $query->withChannels(array($this->channel));
    $query->setLimit(1000);
    $logs = $query->execute();

    require_celerity_resource('phabricator-chatlog-css');

    $last_author = null;
    $last_epoch = null;

    $row_idx = 0;
    $row_colors = array(
      'normal',
      'alternate',
    );

    $out = array();
    $out[] = '<table class="phabricator-chat-log">';
    foreach ($logs as $log) {
      $this_author = $log->getAuthor();
      $this_epoch  = $log->getEpoch();

      if (($this_author !== $last_author) ||
          ($this_epoch - (60 * 5) > $last_epoch)) {
        ++$row_idx;
        $out[] = '<tr class="initial '.$row_colors[$row_idx % 2].'">';
        $out[] = '<td class="timestamp">'.
          phabricator_datetime($log->getEpoch(), $user).'</td>';

        $author = $log->getAuthor();
        $author = phutil_utf8_shorten($author, 18);
        $out[] = '<td class="author">'.
          phutil_escape_html($author).'</td>';
      } else {
        $out[] = '<tr class="'.$row_colors[$row_idx % 2].'">';
        $out[] = '<td class="similar" colspan="2"></td>';
      }
      $out[] = '<td class="message">'.
        phutil_escape_html($log->getMessage()).'</td>';
      $out[] = '</tr>';

      $last_author = $this_author;
      $last_epoch  = $this_epoch;
    }
    $out[] = '</table>';


    return $this->buildStandardPageResponse(
      implode("\n", $out),
      array(
        'title' => 'Channel Log',
      ));
  }
}
