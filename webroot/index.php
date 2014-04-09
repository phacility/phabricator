<?php

$phabricator_root = dirname(dirname(__FILE__));
require_once $phabricator_root.'/support/PhabricatorStartup.php';

// If the preamble script exists, load it.
$preamble_path = $phabricator_root.'/support/preamble.php';
if (file_exists($preamble_path)) {
  require_once $preamble_path;
}

PhabricatorStartup::didStartup();

$show_unexpected_traces = false;
try {
  PhabricatorStartup::loadCoreLibraries();

  PhabricatorEnv::initializeWebEnvironment();
  $show_unexpected_traces = PhabricatorEnv::getEnvConfig(
    'phabricator.developer-mode');

  // This is the earliest we can get away with this, we need env config first.
  PhabricatorAccessLog::init();
  $access_log = PhabricatorAccessLog::getLog();
  PhabricatorStartup::setGlobal('log.access', $access_log);
  $access_log->setData(
    array(
      'R' => AphrontRequest::getHTTPHeader('Referer', '-'),
      'r' => idx($_SERVER, 'REMOTE_ADDR', '-'),
      'M' => idx($_SERVER, 'REQUEST_METHOD', '-'),
    ));

  DarkConsoleXHProfPluginAPI::hookProfiler();
  DarkConsoleErrorLogPluginAPI::registerErrorHandler();

  $sink = new AphrontPHPHTTPSink();

  $response = PhabricatorSetupCheck::willProcessRequest();
  if ($response) {
    PhabricatorStartup::endOutputCapture();
    $sink->writeResponse($response);
    return;
  }

  $host = AphrontRequest::getHTTPHeader('Host');
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

  // Until an administrator sets "phabricator.base-uri", assume it is the same
  // as the request URI. This will work fine in most cases, it just breaks down
  // when daemons need to do things.
  $request_protocol = ($request->isHTTPS() ? 'https' : 'http');
  $request_base_uri = "{$request_protocol}://{$host}/";
  PhabricatorEnv::setRequestBaseURI($request_base_uri);

  $write_guard = new AphrontWriteGuard(array($request, 'validateCSRF'));

  $application->setRequest($request);
  list($controller, $uri_data) = $application->buildController();

  $access_log->setData(
    array(
      'U' => (string)$request->getRequestURI()->getPath(),
      'C' => get_class($controller),
    ));

  // If execution throws an exception and then trying to render that exception
  // throws another exception, we want to show the original exception, as it is
  // likely the root cause of the rendering exception.
  $original_exception = null;
  try {
    $response = $controller->willBeginExecution();

    if ($request->getUser() && $request->getUser()->getPHID()) {
      $access_log->setData(
        array(
          'u' => $request->getUser()->getUserName(),
          'P' => $request->getUser()->getPHID(),
        ));
    }

    if (!$response) {
      $controller->willProcessRequest($uri_data);
      $response = $controller->processRequest();
    }
  } catch (Exception $ex) {
    $original_exception = $ex;
    $response = $application->handleException($ex);
  }

  try {
    $response = $controller->didProcessRequest($response);
    $response = $application->willSendResponse($response, $controller);
    $response->setRequest($request);

    $unexpected_output = PhabricatorStartup::endOutputCapture();
    if ($unexpected_output) {
      $unexpected_output = "Unexpected output:\n\n{$unexpected_output}";
      phlog($unexpected_output);

      if ($response instanceof AphrontWebpageResponse) {
        echo phutil_tag(
          'div',
          array('style' =>
            'background: #eeddff;'.
            'white-space: pre-wrap;'.
            'z-index: 200000;'.
            'position: relative;'.
            'padding: 8px;'.
            'font-family: monospace'),
          $unexpected_output);
      }
    }

    $sink->writeResponse($response);
  } catch (Exception $ex) {
    $write_guard->dispose();
    $access_log->write();
    if ($original_exception) {
      $ex = new PhutilAggregateException(
        "Multiple exceptions during processing and rendering.",
        array(
          $original_exception,
          $ex,
        ));
    }
    PhabricatorStartup::didEncounterFatalException(
      'Rendering Exception',
      $ex,
      $show_unexpected_traces);
  }

  $write_guard->dispose();

  $access_log->setData(
    array(
      'c' => $response->getHTTPResponseCode(),
      'T' => PhabricatorStartup::getMicrosecondsSinceStart(),
    ));

  DarkConsoleXHProfPluginAPI::saveProfilerSample($access_log);

  // Add points to the rate limits for this request.
  if (isset($_SERVER['REMOTE_ADDR'])) {
    $user_ip = $_SERVER['REMOTE_ADDR'];

    // The base score for a request allows users to make 30 requests per
    // minute.
    $score = (1000 / 30);

    // If the user was logged in, let them make more requests.
    if ($request->getUser() && $request->getUser()->getPHID()) {
      $score = $score / 5;
    }

    PhabricatorStartup::addRateLimitScore($user_ip, $score);
  }

} catch (Exception $ex) {
  PhabricatorStartup::didEncounterFatalException(
    'Core Exception',
    $ex,
    $show_unexpected_traces);
}
