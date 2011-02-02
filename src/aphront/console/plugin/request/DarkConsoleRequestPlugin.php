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
      ),
    );

    $sections = array_merge($sections, $data);

/*
    $out = <x:frag />;
    foreach ($sections as $header => $map) {
      $list = <table class="LConsoleRequestDict" />;
      foreach ($map as $key => $value) {
        if (!is_scalar($value)) {
          $value = fb_json_encode($value);
        }
        $value = <text wrap="80">{$value}</text>;
        $list->appendChild(
          <tr><th>{$key}</th><td>{$value}</td></tr>);
      }
      $out->appendChild(
        <x:frag>
          <h1>{$header}</h1>
          {$list}
        </x:frag>);
    }

    return $out;
*/
    return "REQUEST";
  }
}
