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

class DarkConsoleConfigPlugin extends DarkConsolePlugin {

  public function getName() {
    return 'Config';
  }

  public function getDescription() {
    return 'Information about Phabricator configuration';
  }

  public function generateData() {
    return PhabricatorEnv::getAllConfigKeys();
  }

  public function render() {

    $data = $this->getData();
    ksort($data);
    
    $mask = PhabricatorEnv::getEnvConfig('darkconsole.config-mask');
    $mask = array_fill_keys($mask, true);
    
    $rows = array();
    foreach ($data as $key => $value) {
      if (empty($mask[$key])) {
        $display_value = is_array($value) ? json_encode($value) : $value;
        $display_value = phutil_escape_html($display_value);
      } else {
        $display_value = phutil_escape_html('<Masked>');
      }
      $rows[] = array(
        phutil_escape_html($key),
        $display_value,
      );
    }

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        'Key',
        'Value',
      ));
    $table->setColumnClasses(
      array(
        'header',
        'wide wrap',
      ));

    return $table->render();
  }
}
