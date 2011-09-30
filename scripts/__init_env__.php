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

$include_path = ini_get('include_path');
ini_set('include_path', $include_path.':'.dirname(__FILE__).'/../../');

require_once dirname(dirname(__FILE__)).'/conf/__init_conf__.php';

$env = isset($_SERVER['PHABRICATOR_ENV'])
  ? $_SERVER['PHABRICATOR_ENV']
  : getenv('PHABRICATOR_ENV');
if (!$env) {
  echo "Define PHABRICATOR_ENV before running this script.\n";
  exit(1);
}

$conf = phabricator_read_config_file($env);
$conf['phabricator.env'] = $env;

phutil_require_module('phabricator', 'infrastructure/env');
PhabricatorEnv::setEnvConfig($conf);

phutil_load_library('arcanist/src');

foreach (PhabricatorEnv::getEnvConfig('load-libraries') as $library) {
  phutil_load_library($library);
}

PhutilErrorHandler::initialize();
PhabricatorEventEngine::initialize();

$tz = PhabricatorEnv::getEnvConfig('phabricator.timezone');
if ($tz) {
  date_default_timezone_set($tz);
}

