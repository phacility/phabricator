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

final class PhabricatorDaemonLogListView extends AphrontView {

  private $daemonLogs;
  private $user;

  public function setDaemonLogs(array $daemon_logs) {
    assert_instances_of($daemon_logs, 'PhabricatorDaemonLog');
    $this->daemonLogs = $daemon_logs;
    return $this;
  }

  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
  }

  public function render() {
    $rows = array();

    if (!$this->user) {
      throw new Exception("Call setUser() before rendering!");
    }

    foreach ($this->daemonLogs as $log) {
      $epoch = $log->getDateCreated();

      if ($log->getHost() == php_uname('n')) {

        $pid = $log->getPID();
        $is_running = PhabricatorDaemonReference::isProcessRunning($pid);

        if ($is_running) {
          $running = phutil_render_tag(
            'span',
            array(
              'style' => 'color: #00cc00',
              'title' => 'Running',
            ),
            '&bull;');
        } else {
          $running = phutil_render_tag(
            'span',
            array(
              'style' => 'color: #cc0000',
              'title' => 'Not running',
            ),
            '&bull;');
        }
      } else {
        $running = phutil_render_tag(
          'span',
          array(
            'style' => 'color: #888888',
            'title' => 'Not on this host',
          ),
          '?');
      }

      $rows[] = array(
        $running,
        phutil_escape_html($log->getDaemon()),
        phutil_escape_html($log->getHost()),
        $log->getPID(),
        phabricator_date($epoch, $this->user),
        phabricator_time($epoch, $this->user),
        phutil_render_tag(
          'a',
          array(
            'href' => '/daemon/log/'.$log->getID().'/',
            'class' => 'button small grey',
          ),
          'View Log'),
      );
    }

    $daemon_table = new AphrontTableView($rows);
    $daemon_table->setHeaders(
      array(
        '',
        'Daemon',
        'Host',
        'PID',
        'Date',
        'Time',
        'View',
      ));
    $daemon_table->setColumnClasses(
      array(
        '',
        'wide wrap',
        '',
        '',
        '',
        'right',
        'action',
      ));

    return $daemon_table->render();
  }

}
