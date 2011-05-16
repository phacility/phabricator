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

class DarkConsoleServicesPlugin extends DarkConsolePlugin {

  protected $observations;

  public function getName() {
    return 'Services';
  }

  public function getDescription() {
    return 'Information about services.';
  }

  public function generateData() {
    return PhutilServiceProfiler::getInstance()->getServiceCallLog();
  }

  public function render() {
    $data = $this->getData();

    $rows = array();
    foreach ($data as $row) {

      switch ($row['type']) {
        case 'query':
          $info = $row['query'];
          $info = phutil_escape_html($info);
          break;
        case 'connect':
          $info = $row['host'].':'.$row['database'];
          $info = phutil_escape_html($info);
          break;
        case 'exec':
          $info = $row['command'];
          $info = phutil_escape_html($info);
          break;
        case 'conduit':
          $info = $row['method'];
          $info = phutil_escape_html($info);
          break;
        default:
          $info = '-';
          break;
      }

      $rows[] = array(
        phutil_escape_html($row['type']),
        number_format(1000000 * $row['duration']).' us',
        $info,
      );
    }

    $table = new AphrontTableView($rows);
    $table->setColumnClasses(
      array(
        null,
        'n',
        'wide wrap',
      ));
    $table->setHeaders(
      array(
        'Event',
        'Duration',
        'Details',
      ));

    return $table->render();
  }
}

