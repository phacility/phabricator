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

class DarkConsoleErrorLogPlugin extends DarkConsolePlugin {

  public function getName() {
    $count = count($this->getData());

/*
    if ($count) {
      return
        <x:frag>
          <span style="color: #ff0000;">&bull;</span> Error Log ({$count})
        </x:frag>;
    }

*/
    return 'Error Log';
  }


  public function getDescription() {
    return 'Shows errors and warnings.';
  }


  public function generateData() {
/*
    $stub = tabconsole();
    if (!$stub) {
      return array();
    }

    $errors = $stub->getErrors();

    $data = array();
    foreach ($errors as $error) {
      if (is_array($error)) {
        list($err, $trace) = $error;
        $trace = implode("\n", $trace);
      } else {
        $err = $error->getMessage();
        $trace = $error->getTraceAsString();
      }
      $data[] = array(
        'error' => $err,
        'trace' => $trace,
      );
    }
    return $data;
*/
  }


  public function render() {

    return '!!';
/*
    $data = $this->getData();
    if (!$data) {
      return
        <x:frag>
          <div class="mu">No errors.</div>
        </x:frag>;
    }

    $markup = <table class="LConsoleErrors" />;
    $alt = false;
    foreach ($data as $error) {
      $row = <tr class={$alt ? 'alt' : null} />;

      $text = $error['error'];
      $text = preg_replace('/\(in .* on line \d+\)$/', '', trim($text));

      $trace = $error['trace'];
      $trace = explode("\n", $trace);
      if (!$trace) {
        $trace = array('unknown@0@unknown');
      }

      foreach ($trace as $idx => $traceline) {
        list($file, $line, $where) = array_merge(
          explode('@', $traceline),
          array('?', '?', '?'));
        if ($where == 'DarkConsole->addError' ||
            $where == 'debug_rlog') {
          unset($trace[$idx]);
        }
      }

      $row->appendChild(<th rowspan={count($trace)}>{$text}</th>);

      foreach ($trace as $traceline) {
        list($file, $line, $where) = array_merge(
          explode('@', $traceline),
          array('?', '?', '?'));
        $row->appendChild(<td>{$file}:{$line}</td>);
        $row->appendChild(<td>{$where}()</td>);
        $markup->appendChild($row);
        $row = <tr class={$alt ? 'alt' : null} />;
      }

      $alt = !$alt;
    }

    return
      <x:frag>
        <h1>Errors</h1>
        <div class="LConsoleErrors">{$markup}</div>
      </x:frag>;
*/
  }

}
