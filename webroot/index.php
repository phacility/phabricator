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

$env = getenv('PHABRICATOR_ENV'); // Apache

if (!$env) {
  phabricator_fatal_config_error(
    "The 'PHABRICATOR_ENV' environmental variable is not defined. Modify ".
    "your httpd.conf to include 'SetEnv PHABRICATOR_ENV <env>', where '<env>' ".
    "is one of 'development', 'production', or a custom environment.");
}

if (!function_exists('mysql_connect')) {
  phabricator_fatal_config_error(
    "The PHP MySQL extension is not installed. This extension is required.");
}

if (!isset($_REQUEST['__path__'])) {
  phabricator_fatal_config_error(
    "__path__ is not set. Your rewrite rules are not configured correctly.");
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

foreach (PhabricatorEnv::getEnvConfig('load-libraries') as $library) {
  phutil_load_library($library);
}


$host = $_SERVER['HTTP_HOST'];
$path = $_REQUEST['__path__'];

switch ($host) {
  default:
    $config_key = 'aphront.default-application-configuration-class';
    $config_class = PhabricatorEnv::getEnvConfig($config_key);
    PhutilSymbolLoader::loadClass($config_class);
    $application = newv($config_class, array());
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

// TODO: This shouldn't be possible in a production-configured environment.
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

  $root = null;
  if (!empty($_SERVER['PHUTIL_LIBRARY_ROOT'])) {
    $root = $_SERVER['PHUTIL_LIBRARY_ROOT'];
  }

  ini_set('include_path', $libraries_root.':'.ini_get('include_path'));
  @include_once $root.'libphutil/src/__phutil_library_init__.php';
  if (!@constant('__LIBPHUTIL__')) {
    echo "ERROR: Unable to load libphutil. Update your PHP 'include_path' to ".
         "include the parent directory of libphutil/.\n";
    exit(1);
  }

  // Load Phabricator itself using the absolute path, so we never end up doing
  // anything surprising (loading index.php and libraries from different
  // directories).
  phutil_load_library($aphront_root.'/src');
  phutil_load_library('arcanist/src');
}

function __autoload($class_name) {
  PhutilSymbolLoader::loadClass($class_name);
}

function phabricator_fatal_config_error($msg) {
  header('Content-Type: text/plain', $replace = true, $http_error = 500);
  $error = "CONFIG ERROR: ".$msg."\n";

  error_log($error);
  echo $error;

  die();
}

