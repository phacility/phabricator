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
class DarkConsoleConfigPlugin extends DarkConsolePlugin {

  public function getName() {
    return 'Config';
  }

  public function getDescription() {
    return 'Information about Phabricator configuration';
  }

  public function generateData() {
    $lib_data = array();
    foreach (PhutilBootloader::getInstance()->getAllLibraries() as $lib) {
      $lib_data[$lib] = phutil_get_library_root($lib);
    }
    return array(
      'config' => PhabricatorEnv::getAllConfigKeys(),
      'libraries' => $lib_data,
    );
  }

  public function render() {

    $data = $this->getData();

    $lib_data = $data['libraries'];

    $lib_rows = array();
    foreach ($lib_data as $key => $value) {
      $lib_rows[] = array(
        phutil_escape_html($key),
        phutil_escape_html($value),
      );
    }

    $lib_table = new AphrontTableView($lib_rows);
    $lib_table->setHeaders(
      array(
        'Library',
        'Loaded From',
      ));
    $lib_table->setColumnClasses(
      array(
        'header',
        'wide wrap',
      ));

    $config_data = $data['config'];
    ksort($config_data);

    $mask = PhabricatorEnv::getEnvConfig('darkconsole.config-mask');
    $mask = array_fill_keys($mask, true);

    foreach ($mask as $masked_key => $ignored) {
      if (!PhabricatorEnv::envConfigExists($masked_key)) {
        throw new Exception(
          "Configuration 'darkconsole.config-mask' masks unknown ".
          "configuration key '".$masked_key."'. If this key has been ".
          "renamed, you might be accidentally exposing information which you ".
          "don't intend to.");
      }
    }

    $rows = array();
    foreach ($config_data as $key => $value) {
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

    return $lib_table->render().$table->render();
  }
}
