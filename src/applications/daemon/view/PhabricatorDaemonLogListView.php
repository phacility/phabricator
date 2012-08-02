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

      $status = $log->getStatus();
      if ($log->getHost() == php_uname('n') &&
          $status != PhabricatorDaemonLog::STATUS_EXITED &&
          $status != PhabricatorDaemonLog::STATUS_DEAD) {

        $pid = $log->getPID();
        $is_running = PhabricatorDaemonReference::isProcessRunning($pid);
        if (!$is_running) {
          $guard = AphrontWriteGuard::beginScopedUnguardedWrites();
          $log->setStatus(PhabricatorDaemonLog::STATUS_DEAD);
          $log->save();
          unset($guard);
          $status = PhabricatorDaemonLog::STATUS_DEAD;
        }
      }

      $heartbeat_timeout =
        $log->getDateModified() + 3 * PhutilDaemonOverseer::HEARTBEAT_WAIT;
      if ($status == PhabricatorDaemonLog::STATUS_RUNNING &&
          $heartbeat_timeout < time()) {
        $status = PhabricatorDaemonLog::STATUS_UNKNOWN;
      }

      switch ($status) {
        case PhabricatorDaemonLog::STATUS_RUNNING:
          $style = 'color: #00cc00';
          $title = 'Running';
          $symbol = '&bull;';
          break;
        case PhabricatorDaemonLog::STATUS_DEAD:
          $style = 'color: #cc0000';
          $title = 'Died';
          $symbol = '&bull;';
          break;
        case PhabricatorDaemonLog::STATUS_EXITED:
          $style = 'color: #000000';
          $title = 'Exited';
          $symbol = '&bull;';
          break;
        case PhabricatorDaemonLog::STATUS_UNKNOWN:
        default: // fallthrough
          $style = 'color: #888888';
          $title = 'Unknown';
          $symbol = '?';
      }

      $running = phutil_render_tag(
        'span',
        array(
          'style' => $style,
          'title' => $title,
        ),
        $symbol);

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
