<?php

/**
 * @task  routing URI Routing
 */
abstract class AphrontApplicationConfiguration {

  private $request;
  private $host;
  private $path;
  private $console;

  abstract public function getApplicationName();
  abstract public function buildRequest();
  abstract public function build404Controller();
  abstract public function buildRedirectController($uri, $external);

  final public function setRequest(AphrontRequest $request) {
    $this->request = $request;
    return $this;
  }

  final public function getRequest() {
    return $this->request;
  }

  final public function getConsole() {
    return $this->console;
  }

  final public function setConsole($console) {
    $this->console = $console;
    return $this;
  }

  final public function setHost($host) {
    $this->host = $host;
    return $this;
  }

  final public function getHost() {
    return $this->host;
  }

  final public function setPath($path) {
    $this->path = $path;
    return $this;
  }

  final public function getPath() {
    return $this->path;
  }

  public function willBuildRequest() {}


  /**
   * @phutil-external-symbol class PhabricatorStartup
   */
  public static function runHTTPRequest(AphrontHTTPSink $sink) {
    $multimeter = MultimeterControl::newInstance();
    $multimeter->setEventContext('<http-init>');
    $multimeter->setEventViewer('<none>');

    PhabricatorEnv::initializeWebEnvironment();

    $multimeter->setSampleRate(
      PhabricatorEnv::getEnvConfig('debug.sample-rate'));

    $debug_time_limit = PhabricatorEnv::getEnvConfig('debug.time-limit');
    if ($debug_time_limit) {
      PhabricatorStartup::setDebugTimeLimit($debug_time_limit);
    }

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

    // Build the server URI implied by the request headers. If an administrator
    // has not configured "phabricator.base-uri" yet, we'll use this to generate
    // links.

    $request_protocol = ($request->isHTTPS() ? 'https' : 'http');
    $request_base_uri = "{$request_protocol}://{$host}/";
    PhabricatorEnv::setRequestBaseURI($request_base_uri);

    $access_log->setData(
      array(
        'U' => (string)$request->getRequestURI()->getPath(),
      ));

    $write_guard = new AphrontWriteGuard(array($request, 'validateCSRF'));

    $processing_exception = null;
    try {
      $response = $application->processRequest(
        $request,
        $access_log,
        $sink,
        $multimeter);
      $response_code = $response->getHTTPResponseCode();
    } catch (Exception $ex) {
      $processing_exception = $ex;
      $response_code = 500;
    }

    $write_guard->dispose();

    $access_log->setData(
      array(
        'c' => $response_code,
        'T' => PhabricatorStartup::getMicrosecondsSinceStart(),
      ));

    $multimeter->newEvent(
      MultimeterEvent::TYPE_REQUEST_TIME,
      $multimeter->getEventContext(),
      PhabricatorStartup::getMicrosecondsSinceStart());

    $access_log->write();

    $multimeter->saveEvents();

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

    if ($processing_exception) {
      throw $processing_exception;
    }
  }


  public function processRequest(
    AphrontRequest $request,
    PhutilDeferredLog $access_log,
    AphrontHTTPSink $sink,
    MultimeterControl $multimeter) {

    $this->setRequest($request);

    list($controller, $uri_data) = $this->buildController();

    $controller_class = get_class($controller);
    $access_log->setData(
      array(
        'C' => $controller_class,
      ));
    $multimeter->setEventContext('web.'.$controller_class);

    $request->setURIMap($uri_data);
    $controller->setRequest($request);

    // If execution throws an exception and then trying to render that
    // exception throws another exception, we want to show the original
    // exception, as it is likely the root cause of the rendering exception.
    $original_exception = null;
    try {
      $response = $controller->willBeginExecution();

      if ($request->getUser() && $request->getUser()->getPHID()) {
        $access_log->setData(
          array(
            'u' => $request->getUser()->getUserName(),
            'P' => $request->getUser()->getPHID(),
          ));
        $multimeter->setEventViewer('user.'.$request->getUser()->getPHID());
      }

      if (!$response) {
        $controller->willProcessRequest($uri_data);
        $response = $controller->handleRequest($request);
      }
    } catch (Exception $ex) {
      $original_exception = $ex;
      $response = $this->handleException($ex);
    }

    try {
      $response = $controller->didProcessRequest($response);
      $response = $this->willSendResponse($response, $controller);
      $response->setRequest($request);

      $unexpected_output = PhabricatorStartup::endOutputCapture();
      if ($unexpected_output) {
        $unexpected_output = pht(
          "Unexpected output:\n\n%s",
          $unexpected_output);

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
              'font-family: monospace',
            ),
            $unexpected_output);
        }
      }

      $sink->writeResponse($response);
    } catch (Exception $ex) {
      if ($original_exception) {
        throw $original_exception;
      }
      throw $ex;
    }

    return $response;
  }


/* -(  URI Routing  )-------------------------------------------------------- */


  /**
   * Using builtin and application routes, build the appropriate
   * @{class:AphrontController} class for the request. To route a request, we
   * first test if the HTTP_HOST is configured as a valid Phabricator URI. If
   * it isn't, we do a special check to see if it's a custom domain for a blog
   * in the Phame application and if that fails we error. Otherwise, we test
   * against all application routes from installed
   * @{class:PhabricatorApplication}s.
   *
   * If we match a route, we construct the controller it points at, build it,
   * and return it.
   *
   * If we fail to match a route, but the current path is missing a trailing
   * "/", we try routing the same path with a trailing "/" and do a redirect
   * if that has a valid route. The idea is to canoncalize URIs for consistency,
   * but avoid breaking noncanonical URIs that we can easily salvage.
   *
   * NOTE: We only redirect on GET. On POST, we'd drop parameters and most
   * likely mutate the request implicitly, and a bad POST usually indicates a
   * programming error rather than a sloppy typist.
   *
   * If the failing path already has a trailing "/", or we can't route the
   * version with a "/", we call @{method:build404Controller}, which build a
   * fallback @{class:AphrontController}.
   *
   * @return pair<AphrontController,dict> Controller and dictionary of request
   *                                      parameters.
   * @task routing
   */
  final public function buildController() {
    $request = $this->getRequest();

    // If we're configured to operate in cluster mode, reject requests which
    // were not received on a cluster interface.
    //
    // For example, a host may have an internal address like "170.0.0.1", and
    // also have a public address like "51.23.95.16". Assuming the cluster
    // is configured on a range like "170.0.0.0/16", we want to reject the
    // requests received on the public interface.
    //
    // Ideally, nodes in a cluster should only be listening on internal
    // interfaces, but they may be configured in such a way that they also
    // listen on external interfaces, since this is easy to forget about or
    // get wrong. As a broad security measure, reject requests received on any
    // interfaces which aren't on the whitelist.

    $cluster_addresses = PhabricatorEnv::getEnvConfig('cluster.addresses');
    if ($cluster_addresses) {
      $server_addr = idx($_SERVER, 'SERVER_ADDR');
      if (!$server_addr) {
        if (php_sapi_name() == 'cli') {
          // This is a command line script (probably something like a unit
          // test) so it's fine that we don't have SERVER_ADDR defined.
        } else {
          throw new AphrontUsageException(
            pht('No SERVER_ADDR'),
            pht(
              'Phabricator is configured to operate in cluster mode, but '.
              'SERVER_ADDR is not defined in the request context. Your '.
              'webserver configuration needs to forward SERVER_ADDR to '.
              'PHP so Phabricator can reject requests received on '.
              'external interfaces.'));
        }
      } else {
        if (!PhabricatorEnv::isClusterAddress($server_addr)) {
          throw new AphrontUsageException(
            pht('External Interface'),
            pht(
              'Phabricator is configured in cluster mode and the address '.
              'this request was received on ("%s") is not whitelisted as '.
              'a cluster address.',
              $server_addr));
        }
      }
    }

    if (PhabricatorEnv::getEnvConfig('security.require-https')) {
      if (!$request->isHTTPS()) {
        $https_uri = $request->getRequestURI();
        $https_uri->setDomain($request->getHost());
        $https_uri->setProtocol('https');

        // In this scenario, we'll be redirecting to HTTPS using an absolute
        // URI, so we need to permit an external redirect.
        return $this->buildRedirectController($https_uri, true);
      }
    }

    $path         = $request->getPath();
    $host         = $request->getHost();
    $base_uri     = PhabricatorEnv::getEnvConfig('phabricator.base-uri');
    $prod_uri     = PhabricatorEnv::getEnvConfig('phabricator.production-uri');
    $file_uri     = PhabricatorEnv::getEnvConfig(
      'security.alternate-file-domain');
    $allowed_uris = PhabricatorEnv::getEnvConfig('phabricator.allowed-uris');

    $uris = array_merge(
      array(
        $base_uri,
        $prod_uri,
      ),
      $allowed_uris);

    $cdn_routes = array(
      '/res/',
      '/file/data/',
      '/file/xform/',
      '/phame/r/',
      );

    $host_match = false;
    foreach ($uris as $uri) {
      if ($host === id(new PhutilURI($uri))->getDomain()) {
        $host_match = true;
        break;
      }
    }

    if (!$host_match) {
      if ($host === id(new PhutilURI($file_uri))->getDomain()) {
        foreach ($cdn_routes as $route) {
          if (strncmp($path, $route, strlen($route)) == 0) {
            $host_match = true;
            break;
          }
        }
      }
    }

    // NOTE: If the base URI isn't defined yet, don't activate alternate
    // domains.
    if ($base_uri && !$host_match) {

      try {
        $blog = id(new PhameBlogQuery())
          ->setViewer(new PhabricatorUser())
          ->withDomain($host)
          ->executeOne();
      } catch (PhabricatorPolicyException $ex) {
        throw new Exception(
          'This blog is not visible to logged out users, so it can not be '.
          'visited from a custom domain.');
      }

      if (!$blog) {
        if ($prod_uri && $prod_uri != $base_uri) {
          $prod_str = ' or '.$prod_uri;
        } else {
          $prod_str = '';
        }
        throw new Exception(
          'Specified domain '.$host.' is not configured for Phabricator '.
          'requests. Please use '.$base_uri.$prod_str.' to visit this instance.'
        );
      }

      // TODO: Make this more flexible and modular so any application can
      // do crazy stuff here if it wants.

      $path = '/phame/live/'.$blog->getID().'/'.$path;
    }

    list($controller, $uri_data) = $this->buildControllerForPath($path);
    if (!$controller) {
      if (!preg_match('@/$@', $path)) {
        // If we failed to match anything but don't have a trailing slash, try
        // to add a trailing slash and issue a redirect if that resolves.
        list($controller, $uri_data) = $this->buildControllerForPath($path.'/');

        // NOTE: For POST, just 404 instead of redirecting, since the redirect
        // will be a GET without parameters.

        if ($controller && !$request->isHTTPPost()) {
          $slash_uri = $request->getRequestURI()->setPath($path.'/');

          $external = strlen($request->getRequestURI()->getDomain());
          return $this->buildRedirectController($slash_uri, $external);
        }
      }
      return $this->build404Controller();
    }

    return array($controller, $uri_data);
  }


  /**
   * Map a specific path to the corresponding controller. For a description
   * of routing, see @{method:buildController}.
   *
   * @return pair<AphrontController,dict> Controller and dictionary of request
   *                                      parameters.
   * @task routing
   */
  final public function buildControllerForPath($path) {
    $maps = array();

    $applications = PhabricatorApplication::getAllInstalledApplications();
    foreach ($applications as $application) {
      $maps[] = array($application, $application->getRoutes());
    }

    $current_application = null;
    $controller_class = null;
    foreach ($maps as $map_info) {
      list($application, $map) = $map_info;

      $mapper = new AphrontURIMapper($map);
      list($controller_class, $uri_data) = $mapper->mapPath($path);

      if ($controller_class) {
        if ($application) {
          $current_application = $application;
        }
        break;
      }
    }

    if (!$controller_class) {
      return array(null, null);
    }

    $request = $this->getRequest();

    $controller = newv($controller_class, array());
    if ($current_application) {
      $controller->setCurrentApplication($current_application);
    }

    return array($controller, $uri_data);
  }

}
