<?php

require_once dirname(dirname(__FILE__)).'/support/PhabricatorStartup.php';
PhabricatorStartup::didStartup();

$access_log = null;

$env = getenv('PHABRICATOR_ENV'); // Apache
if (!$env) {
  if (isset($_ENV['PHABRICATOR_ENV'])) {
    $env = $_ENV['PHABRICATOR_ENV'];
  }
}

if (!$env) {
  PhabricatorStartup::didFatal(
    "The 'PHABRICATOR_ENV' environmental variable is not defined. Modify ".
    "your httpd.conf to include 'SetEnv PHABRICATOR_ENV <env>', where '<env>' ".
    "is one of 'development', 'production', or a custom environment.");
}


require_once dirname(dirname(__FILE__)).'/conf/__init_conf__.php';

try {
  setup_aphront_basics();

  $overseer = new PhabricatorRequestOverseer();
  $overseer->didStartup();

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
    PhabricatorStartup::setGlobal('log.access', $access_log);
    $access_log->setData(
      array(
        'R' => idx($_SERVER, 'HTTP_REFERER', '-'),
        'r' => idx($_SERVER, 'REMOTE_ADDR', '-'),
        'M' => idx($_SERVER, 'REQUEST_METHOD', '-'),
      ));
  }

  DarkConsoleXHProfPluginAPI::hookProfiler();

  PhutilErrorHandler::initialize();

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
    $response = $controller->didProcessRequest($response);
    $response = $application->willSendResponse($response, $controller);
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
    PhabricatorStartup::didFatal('[Rendering Exception] '.$ex->getMessage());
  }

  $write_guard->dispose();

  // TODO: Share the $sink->writeResponse() pathway here?

  $sink = new AphrontPHPHTTPSink();
  $sink->writeHTTPStatus($response->getHTTPResponseCode());

  $headers = $response->getCacheHeaders();
  $headers = array_merge($headers, $response->getHeaders());

  $sink->writeHeaders($headers);

  $sink->writeData($response_string);

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

