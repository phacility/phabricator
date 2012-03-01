#!/usr/bin/env php
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

$root = dirname(dirname(dirname(__FILE__)));
require_once $root.'/scripts/__init_script__.php';

if ($argc !== 2 || $argv[1] === '--help') {
  echo "Usage: aphrontpath.php <url>\n";
  echo "Purpose: Print controller which will process passed <url>.\n";
  exit(1);
}

$url = parse_url($argv[1]);
$path = '/'.(isset($url['path']) ? ltrim($url['path'], '/') : '');

$config_key = 'aphront.default-application-configuration-class';
$config_class = PhabricatorEnv::getEnvConfig($config_key);
$application = newv($config_class, array());
$mapper = new AphrontURIMapper($application->getURIMap());

list($controller) = $mapper->mapPath($path);
if (!$controller && $path[strlen($path) - 1] !== '/') {
  list($controller) = $mapper->mapPath($path.'/');
}
if ($controller) {
  echo "$controller\n";
}
