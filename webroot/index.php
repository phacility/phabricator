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

error_reporting(E_ALL | E_STRICT);

$env = getenv('PHABRICATOR_ENV');
if (!$env) {
  header('Content-Type: text/plain');
  die(
    "CONFIG ERROR: ".
    "The 'PHABRICATOR_ENV' environmental variable is not defined. Modify ".
    "your httpd.conf to include 'SetEnv PHABRICATOR_ENV <env>', where '<env>' ".
    "is one of 'development', 'production', or a custom environment.");
}

if (!function_exists('mysql_connect')) {
  header('Content-Type: text/plain');
  die(
    "CONFIG ERROR: ".
    "the PHP MySQL extension is not installed. This extension is required.");
}

require_once dirname(dirname(__FILE__)).'/conf/__init_conf__.php';

$conf = phabricator_read_config_file($env);
$conf['phabricator.env'] = $env;

setup_aphront_basics();

phutil_require_module('phabricator', 'infrastructure/env');
PhabricatorEnv::setEnvConfig($conf);

phutil_require_module('phabricator', 'aphront/console/plugin/xhprof/api');
DarkConsoleXHProfPluginAPI::hookProfiler();

phutil_require_module('phabricator', 'aphront/console/plugin/errorlog/api');
set_error_handler(array('DarkConsoleErrorLogPluginAPI', 'handleError'));
set_exception_handler(array('DarkConsoleErrorLogPluginAPI', 'handleException'));

$host = $_SERVER['HTTP_HOST'];
$path = $_REQUEST['__path__'];

// Based on the host and path, choose which application should serve the
// request. The default is the Aphront demo, but you'll want to replace this
// with whichever other applications you're running.

switch ($host) {
  default:
    phutil_require_module('phutil', 'autoload');
    phutil_autoload_class('AphrontDefaultApplicationConfiguration');
    $application = new AphrontDefaultApplicationConfiguration();
    break;
}

$application->setHost($host);
$application->setPath($path);
$application->willBuildRequest();
$request = $application->buildRequest();
$application->setRequest($request);
list($controller, $uri_data) = $application->buildController();
try {
  $controller->willBeginExecution();

  $controller->willProcessRequest($uri_data);
  $response = $controller->processRequest();
} catch (AphrontRedirectException $ex) {
  $response = id(new AphrontRedirectResponse())
    ->setURI($ex->getURI());
} catch (Exception $ex) {
  $response = $application->handleException($ex);
}

$response = $application->willSendResponse($response);

$response->setRequest($request);

$response_string = $response->buildResponseString();

$code = $response->getHTTPResponseCode();
if ($code != 200) {
  header("HTTP/1.0 {$code}");
}

$headers = $response->getCacheHeaders();
$headers = array_merge($headers, $response->getHeaders());
foreach ($headers as $header) {
  list($header, $value) = $header;
  header("{$header}: {$value}");
}

if (isset($_REQUEST['__profile__']) &&
    ($_REQUEST['__profile__'] == 'all')) {
  $profile = DarkConsoleXHProfPluginAPI::stopProfiler();
  $profile =
    '<div style="text-align: center; background: #ff00ff; padding: 1em;
                 font-size: 24px; font-weight: bold;">'.
      '<a href="/xhprof/profile/'.$profile.'/">'.
        '&gt;&gt;&gt; View Profile &lt;&lt;&lt;'.
      '</a>'.
    '</div>';
  if (strpos($response_string, '<body>') !== false) {
    $response_string = str_replace(
      '<body>',
      '<body>'.$profile,
      $response_string);
  } else {
    echo $profile;
  }
}

echo $response_string;


/**
 * @group aphront
 */
function setup_aphront_basics() {
  $aphront_root   = dirname(dirname(__FILE__));
  $libraries_root = dirname($aphront_root);

  ini_set('include_path', ini_get('include_path').':'.$libraries_root.'/');
  @include_once 'libphutil/src/__phutil_library_init__.php';
  if (!@constant('__LIBPHUTIL__')) {
    echo "ERROR: Unable to load libphutil. Update your PHP 'include_path' to ".
         "include the parent directory of libphutil/.\n";
    exit(1);
  }

  if (!ini_get('date.timezone')) {
    date_default_timezone_set('America/Los_Angeles');
  }

  phutil_load_library($libraries_root.'/arcanist/src');
  phutil_load_library($aphront_root.'/src');
}

function __autoload($class_name) {
  PhutilSymbolLoader::loadClass($class_name);
}
