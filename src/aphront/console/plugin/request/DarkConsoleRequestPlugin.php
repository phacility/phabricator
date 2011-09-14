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

/**
 * @group console
 */
class DarkConsoleRequestPlugin extends DarkConsolePlugin {

  public function getName() {
    return 'Request';
  }

  public function getDescription() {
    return 'Information about $_REQUEST and $_SERVER.';
  }

  public function generateData() {
    return array(
      'Request'  => $_REQUEST,
      'Server'   => $_SERVER,
    );
  }

  public function render() {

    $data = $this->getData();

    $sections = array(
      'Basics' => array(
        'Host'      => $data['Server']['SERVER_ADDR'],
        'Hostname'  => gethostbyaddr($data['Server']['SERVER_ADDR']),
        'Machine'   => php_uname('n'),
      ),
    );

    $sections = array_merge($sections, $data);

    $out = array();
    foreach ($sections as $header => $map) {
      $rows = array();
      foreach ($map as $key => $value) {
        $rows[] = array(
          phutil_escape_html($key),
          phutil_escape_html(is_array($value) ? json_encode($value) : $value),
        );
      }

      $table = new AphrontTableView($rows);
      $table->setHeaders(
        array(
          $header,
          null,
        ));
      $table->setColumnClasses(
        array(
          'header',
          'wide wrap',
        ));
      $out[] = $table->render();
    }

    return implode("\n", $out);
  }
}
