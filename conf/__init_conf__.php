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

function phabricator_read_config_file($original_config) {

  $root = dirname(dirname(__FILE__));

  // Accept either "myconfig" (preferred) or "myconfig.conf.php".
  $config = preg_replace('/\.conf\.php$/', '', $original_config);
  $full_config_path = $root.'/conf/'.$config.'.conf.php';

  // Make sure config file errors are reported.
  $old_error_level = error_reporting(E_ALL | E_STRICT);
  $old_display_errors = ini_get('display_errors');
  ini_set('display_errors', 1);

    ob_start();
    $conf = include $full_config_path;
    $errors = ob_get_clean();

  error_reporting($old_error_level);
  ini_set('display_errors', $old_display_errors);

  if ($conf === false) {
    if (!Filesystem::pathExists($full_config_path)) {
      $files = id(new FileFinder($root.'/conf/'))
        ->withType('f')
        ->withSuffix('conf.php')
        ->withFollowSymlinks(true)
        ->find();

      foreach ($files as $key => $file) {
        $file = trim($file, './');
        $files[$key] = preg_replace('/\.conf\.php$/', '', $file);
      }
      $files = "    ".implode("\n    ", $files);

      throw new Exception(
        "CONFIGURATION ERROR\n".
        "Config file '{$original_config}' does not exist. Valid config files ".
        "are:\n\n".$files);
    }
    throw new Exception("Failed to read config file '{$config}': {$errors}");
  }

  return $conf;
}
