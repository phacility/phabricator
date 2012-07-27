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

$__start__ = microtime(true);
$access_log = null;

error_reporting(E_ALL | E_STRICT);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && !$_POST &&
    isset($_REQUEST['__file__'])) {
  $size = ini_get('post_max_size');
  phabricator_fatal(
    "Request size exceeds PHP 'post_max_size' ('{$size}').");
}

$required_version = '5.2.3';
if (version_compare(PHP_VERSION, $required_version) < 0) {
  phabricator_fatal_config_error(
    "You are running PHP version '".PHP_VERSION."', which is older than ".
    "the minimum version, '{$required_version}'. Update to at least ".
    "'{$required_version}'.");
}

phabricator_detect_insane_memory_limit();

ini_set('memory_limit', -1);

$env = getenv('PHABRICATOR_ENV'); // Apache
if (!$env) {
  if (isset($_ENV['PHABRICATOR_ENV'])) {
    $env = $_ENV['PHABRICATOR_ENV'];
  }
}

if (!$env) {
  phabricator_fatal_config_error(
    "The 'PHABRICATOR_ENV' environmental variable is not defined. Modify ".
    "your httpd.conf to include 'SetEnv PHABRICATOR_ENV <env>', where '<env>' ".
    "is one of 'development', 'production', or a custom environment.");
}

if (!isset($_REQUEST['__path__'])) {
  if (php_sapi_name() == 'cli-server') {
    // Compatibility with PHP 5.4+ built-in web server.
    $url = parse_url($_SERVER['REQUEST_URI']);
    $_REQUEST['__path__'] = $url['path'];
  } else {
    phabricator_fatal_config_error(
      "__path__ is not set. Your rewrite rules are not configured correctly.");
  }
}

if (get_magic_quotes_gpc()) {
  phabricator_fatal_config_error(
    "Your server is configured with PHP 'magic_quotes_gpc' enabled. This ".
    "feature is 'highly discouraged' by PHP's developers and you must ".
    "disable it to run Phabricator. Consult the PHP manual for instructions.");
}

register_shutdown_function('phabricator_shutdown');

require_once dirname(dirname(__FILE__)).'/conf/__init_conf__.php';

try {
  setup_aphront_basics();

  $conf = phabricator_read_config_file($env);
  $conf['phabricator.env'] = $env;

  PhabricatorEnv::setEnvConfig($conf);

  // This needs to be done before we create the log, because
  // PhabricatorAccessLog::getLog() calls date()
  $tz = PhabricatorEnv::getEnvConfig('phabricator.timezone');
  if ($tz) {
    date_default_timezone_set($tz);
  }

  // Append any paths to $PATH if we need to.
  $paths = PhabricatorEnv::getEnvConfig('environment.append-paths');
  if (!empty($paths)) {
    $current_env_path = getenv('PATH');
    $new_env_paths = implode(':', $paths);
    putenv('PATH='.$current_env_path.':'.$new_env_paths);
  }

  // This is the earliest we can get away with this, we need env config first.
  PhabricatorAccessLog::init();
  $access_log = PhabricatorAccessLog::getLog();
  if ($access_log) {
    $access_log->setData(
      array(
        'R' => idx($_SERVER, 'HTTP_REFERER', '-'),
        'r' => idx($_SERVER, 'REMOTE_ADDR', '-'),
        'M' => idx($_SERVER, 'REQUEST_METHOD', '-'),
      ));
  }

  DarkConsoleXHProfPluginAPI::hookProfiler();

  PhutilErrorHandler::initialize();

} catch (Exception $ex) {
  phabricator_fatal("[Initialization Exception] ".$ex->getMessage());
}

PhutilErrorHandler::setErrorListener(
  array('DarkConsoleErrorLogPluginAPI', 'handleErrors'));

foreach (PhabricatorEnv::getEnvConfig('load-libraries') as $library) {
  phutil_load_library($library);
}

if (PhabricatorEnv::getEnvConfig('phabricator.setup')) {
  try {
    PhabricatorSetup::runSetup();
  } catch (Exception $ex) {
    echo "EXCEPTION!\n";
    echo $ex;
  }
  return;
}

phabricator_detect_bad_base_uri();

$translation = PhabricatorEnv::newObjectFromConfig('translation.provider');
PhutilTranslator::getInstance()
  ->setLanguage($translation->getLanguage())
  ->addTranslations($translation->getTranslations());

$host = $_SERVER['HTTP_HOST'];
$path = $_REQUEST['__path__'];

switch ($host) {
  default:
    $config_key = 'aphront.default-application-configuration-class';
    $application = PhabricatorEnv::newObjectFromConfig($config_key);
    break;
}


$application->setHost($host);
$application->setPath($path);
$application->willBuildRequest();
$request = $application->buildRequest();

$write_guard = new AphrontWriteGuard(array($request, 'validateCSRF'));
PhabricatorEventEngine::initialize();

$application->setRequest($request);
list($controller, $uri_data) = $application->buildController();

if ($access_log) {
  $access_log->setData(
    array(
      'U' => (string)$request->getRequestURI()->getPath(),
      'C' => get_class($controller),
    ));
}

// If execution throws an exception and then trying to render that exception
// throws another exception, we want to show the original exception, as it is
// likely the root cause of the rendering exception.
$original_exception = null;
try {
  $response = $controller->willBeginExecution();

  if ($access_log) {
    if ($request->getUser() && $request->getUser()->getPHID()) {
      $access_log->setData(
        array(
          'u' => $request->getUser()->getUserName(),
        ));
    }
  }

  if (!$response) {
    $controller->willProcessRequest($uri_data);
    $response = $controller->processRequest();
  }
} catch (AphrontRedirectException $ex) {
  $response = id(new AphrontRedirectResponse())
    ->setURI($ex->getURI());
} catch (Exception $ex) {
  $original_exception = $ex;
  $response = $application->handleException($ex);
}

try {
  $response = $application->willSendResponse($response);
  $response->setRequest($request);
  $response_string = $response->buildResponseString();
} catch (Exception $ex) {
  $write_guard->dispose();
  if ($access_log) {
    $access_log->write();
  }
  if ($original_exception) {
    $ex = new PhutilAggregateException(
      "Multiple exceptions during processing and rendering.",
      array(
        $original_exception,
        $ex,
      ));
  }
  phabricator_fatal('[Rendering Exception] '.$ex->getMessage());
}

$write_guard->dispose();

// TODO: Share the $sink->writeResponse() pathway here?

$sink = new AphrontPHPHTTPSink();
$sink->writeHTTPStatus($response->getHTTPResponseCode());

$headers = $response->getCacheHeaders();
$headers = array_merge($headers, $response->getHeaders());

$sink->writeHeaders($headers);

// TODO: This shouldn't be possible in a production-configured environment.
if (DarkConsoleXHProfPluginAPI::isProfilerRequested() &&
    DarkConsoleXHProfPluginAPI::isProfilerRequested() === 'all') {
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
    $sink->writeData($profile);
  }
}

$sink->writeData($response_string);

if ($access_log) {
  $access_log->setData(
    array(
      'c' => $response->getHTTPResponseCode(),
      'T' => (int)(1000000 * (microtime(true) - $__start__)),
    ));
  $access_log->write();
}

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

  ini_set(
    'include_path',
    $libraries_root.PATH_SEPARATOR.ini_get('include_path'));
  @include_once $root.'libphutil/src/__phutil_library_init__.php';
  if (!@constant('__LIBPHUTIL__')) {
    echo "ERROR: Unable to load libphutil. Put libphutil/ next to ".
         "phabricator/, or update your PHP 'include_path' to include ".
         "the parent directory of libphutil/.\n";
    exit(1);
  }

  // Load Phabricator itself using the absolute path, so we never end up doing
  // anything surprising (loading index.php and libraries from different
  // directories).
  phutil_load_library($aphront_root.'/src');
  phutil_load_library('arcanist/src');

}

function phabricator_fatal_config_error($msg) {
  phabricator_fatal("CONFIG ERROR: ".$msg."\n");
}

function phabricator_detect_bad_base_uri() {
  $conf = PhabricatorEnv::getEnvConfig('phabricator.base-uri');
  $uri = new PhutilURI($conf);
  switch ($uri->getProtocol()) {
    case 'http':
    case 'https':
      break;
    default:
      return phabricator_fatal_config_error(
        "'phabricator.base-uri' is set to '{$conf}', which is invalid. ".
        "The URI must start with 'http://' or 'https://'.");
  }

  if (strpos($uri->getDomain(), '.') === false) {
    phabricator_fatal_config_error(
      "'phabricator.base-uri' is set to '{$conf}', which is invalid. The URI ".
      "must contain a dot ('.'), like 'http://example.com/', not just ".
      "'http://example/'. Some web browsers will not set cookies on domains ".
      "with no TLD, and Phabricator requires cookies for login. ".
      "If you are using localhost, create an entry in the hosts file like ".
      "'127.0.0.1 example.com', and access the localhost with ".
      "'http://example.com/'.");
  }
}

function phabricator_detect_insane_memory_limit() {
  $memory_limit = ini_get('memory_limit');
  $char_limit   = 12;
  if (strlen($memory_limit) <= $char_limit) {
    return;
  }

  // colmdoyle ran into an issue on an Ubuntu box with Suhosin where his
  // 'memory_limit' was set to:
  //
  //   3232323232323232323232323232323232323232323232323232323232323232M
  //
  // Not a typo. A wizard did it.
  //
  // Anyway, with this 'memory_limit', the machine would immediately fatal
  // when executing the ini_set() later. I wasn't able to reproduce this on my
  // EC2 Ubuntu + Suhosin box, but verified that it caused the problem on his
  // machine and that setting it to a more sensible value fixed it. Since I
  // have no idea how to actually trigger the issue, we look for a coarse
  // approximation of it (a memory_limit setting more than 12 characters in
  // length).

  phabricator_fatal_config_error(
    "Your PHP 'memory_limit' is set to something ridiculous ".
    "(\"{$memory_limit}\"). Set it to a more reasonable value (it must be no ".
    "more than {$char_limit} characters long).");
}

function phabricator_shutdown() {
  $event = error_get_last();

  if (!$event) {
    return;
  }

  if ($event['type'] != E_ERROR && $event['type'] != E_PARSE) {
    return;
  }

  $msg = ">>> UNRECOVERABLE FATAL ERROR <<<\n\n";
  if ($event) {
    // Even though we should be emitting this as text-plain, escape things just
    // to be sure since we can't really be sure what the program state is when
    // we get here.
    $msg .= phutil_escape_html($event['message'])."\n\n";
    $msg .= phutil_escape_html($event['file'].':'.$event['line']);
  }

  // flip dem tables
  $msg .= "\n\n\n";
  $msg .= "\xe2\x94\xbb\xe2\x94\x81\xe2\x94\xbb\x20\xef\xb8\xb5\x20\xc2\xaf".
          "\x5c\x5f\x28\xe3\x83\x84\x29\x5f\x2f\xc2\xaf\x20\xef\xb8\xb5\x20".
          "\xe2\x94\xbb\xe2\x94\x81\xe2\x94\xbb";

  phabricator_fatal($msg);
}

function phabricator_fatal($msg) {

  global $access_log;
  if ($access_log) {
    $access_log->setData(
      array(
        'c' => 500,
      ));
    $access_log->write();
  }

  header(
    'Content-Type: text/plain; charset=utf-8',
    $replace = true,
    $http_error = 500);

  error_log($msg);
  echo $msg;

  exit(1);
}

