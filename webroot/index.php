<?php

require_once dirname(dirname(__FILE__)).'/support/PhabricatorStartup.php';
PhabricatorStartup::didStartup();

try {
  PhabricatorStartup::loadCoreLibraries();

  PhabricatorEnv::initializeWebEnvironment();

  // This is the earliest we can get away with this, we need env config first.
  PhabricatorAccessLog::init();
  $access_log = PhabricatorAccessLog::getLog();
  if ($access_log) {
    PhabricatorStartup::setGlobal('log.access', $access_log);
    $access_log->setData(
      array(
        'R' => idx($_SERVER, 'HTTP_REFERER', '-'),
        'r' => idx($_SERVER, 'REMOTE_ADDR', '-'),
        'M' => idx($_SERVER, 'REQUEST_METHOD', '-'),
      ));
  }

  DarkConsoleXHProfPluginAPI::hookProfiler();

  PhutilErrorHandler::setErrorListener(
    array('DarkConsoleErrorLogPluginAPI', 'handleErrors'));

  $sink = new AphrontPHPHTTPSink();

  if (PhabricatorEnv::getEnvConfig('phabricator.setup')) {
    try {
      PhabricatorSetup::runSetup();
    } catch (Exception $ex) {
      echo "EXCEPTION!\n";
      echo $ex;
    }
    return;
  }

  PhabricatorSetupCheck::willProcessRequest();

  phabricator_detect_bad_base_uri();

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
    $response = $controller->didProcessRequest($response);
    $response = $application->willSendResponse($response, $controller);
    $response->setRequest($request);

    $sink->writeResponse($response);

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
    PhabricatorStartup::didFatal('[Rendering Exception] '.$ex->getMessage());
  }

  $write_guard->dispose();

  if ($access_log) {
    $request_start = PhabricatorStartup::getStartTime();
    $access_log->setData(
      array(
        'c' => $response->getHTTPResponseCode(),
        'T' => (int)(1000000 * (microtime(true) - $request_start)),
      ));
    $access_log->write();
  }

  if (DarkConsoleXHProfPluginAPI::isProfilerRequested()) {
    $profile = DarkConsoleXHProfPluginAPI::stopProfiler();
    $profile_sample = id(new PhabricatorXHProfSample())
      ->setFilePHID($profile);
    if (empty($_REQUEST['__profile__'])) {
      $sample_rate = PhabricatorEnv::getEnvConfig('debug.profile-rate');
    } else {
      $sample_rate = 0;
    }
    $profile_sample->setSampleRate($sample_rate);
    if ($access_log) {
      $profile_sample->setUsTotal($access_log->getData('T'))
        ->setHostname($access_log->getData('h'))
        ->setRequestPath($access_log->getData('U'))
        ->setController($access_log->getData('C'))
        ->setUserPHID($request->getUser()->getPHID());
    }
    $profile_sample->save();
  }

} catch (Exception $ex) {
  PhabricatorStartup::didFatal("[Exception] ".$ex->getMessage());
}

function phabricator_detect_bad_base_uri() {
  $conf = PhabricatorEnv::getEnvConfig('phabricator.base-uri');
  $uri = new PhutilURI($conf);
  switch ($uri->getProtocol()) {
    case 'http':
    case 'https':
      break;
    default:
      PhabricatorStartup::didFatal(
        "'phabricator.base-uri' is set to '{$conf}', which is invalid. ".
        "The URI must start with 'http://' or 'https://'.");
      return;
  }

  if (strpos($uri->getDomain(), '.') === false) {
    PhabricatorStartup::didFatal(
      "'phabricator.base-uri' is set to '{$conf}', which is invalid. The URI ".
      "must contain a dot ('.'), like 'http://example.com/', not just ".
      "'http://example/'. Some web browsers will not set cookies on domains ".
      "with no TLD, and Phabricator requires cookies for login. ".
      "If you are using localhost, create an entry in the hosts file like ".
      "'127.0.0.1 example.com', and access the localhost with ".
      "'http://example.com/'.");
  }
}

