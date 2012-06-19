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

final class PhabricatorNotificationStatusController
  extends PhabricatorNotificationController {

  public function processRequest() {

    $uri = PhabricatorEnv::getEnvConfig('notification.server-uri');
    $uri = new PhutilURI($uri);

    $uri->setPath('/status/');

    $future = id(new HTTPSFuture($uri))
      ->setTimeout(3);

    try {
      list($body) = $future->resolvex();
      $body = json_decode($body, true);
      if (!is_array($body)) {
        throw new Exception("Expected JSON response from server!");
      }

      $status = $this->renderServerStatus($body);
    } catch (Exception $ex) {
      $status = new AphrontErrorView();
      $status->setTitle("Notification Server Issue");
      $status->appendChild(
        'Unable to determine server status. This probably means the server '.
        'is not in great shape. The specific issue encountered was:'.
        '<br />'.
        '<br />'.
        '<strong>'.phutil_escape_html(get_class($ex)).'</strong> '.
        nl2br(phutil_escape_html($ex->getMessage())));
    }

    return $this->buildStandardPageResponse(
      $status,
      array(
        'title' => 'Aphlict Server Status',
      ));
  }

  private function renderServerStatus(array $status) {

    $rows = array();
    foreach ($status as $key => $value) {
      $label = phutil_escape_html($key);

      switch ($key) {
        case 'uptime':
          $value /= 1000;
          $value = phabricator_format_relative_time_detailed($value);
          break;
        case 'log':
          $value = phutil_escape_html($value);
          break;
        default:
          $value = phutil_escape_html(number_format($value));
          break;
      }

      $rows[] = array($label, $value);
    }

    $table = new AphrontTableView($rows);
    $table->setColumnClasses(
      array(
        'header',
        'wide',
      ));

    $panel = new AphrontPanelView();
    $panel->setHeader('Server Status');
    $panel->appendChild($table);

    return $panel;
  }
}
