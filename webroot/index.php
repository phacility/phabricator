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

setup_aphront_basics();

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
