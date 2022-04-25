<?php

/**
 * @task routing URI Routing
 * @task response Response Handling
 * @task exception Exception Handling
 */
final class AphrontApplicationConfiguration
  extends Phobject {

  private $request;
  private $host;
  private $path;
  private $console;

  public function buildRequest() {
    $parser = new PhutilQueryStringParser();

    $data = array();
    $data += $_POST;
    $data += $parser->parseQueryString(idx($_SERVER, 'QUERY_STRING', ''));

    $cookie_prefix = PhabricatorEnv::getEnvConfig('phabricator.cookie-prefix');

    $request = new AphrontRequest($this->getHost(), $this->getPath());
    $request->setRequestData($data);
    $request->setApplicationConfiguration($this);
    $request->setCookiePrefix($cookie_prefix);

    $request->updateEphemeralCookies();

    return $request;
  }

  public function buildRedirectController($uri, $external) {
    return array(
      new PhabricatorRedirectController(),
      array(
        'uri' => $uri,
        'external' => $external,
      ),
    );
  }

  public function setRequest(AphrontRequest $request) {
    $this->request = $request;
    return $this;
  }

  public function getRequest() {
    return $this->request;
  }

  public function getConsole() {
    return $this->console;
  }

  public function setConsole($console) {
    $this->console = $console;
    return $this;
  }

  public function setHost($host) {
    $this->host = $host;
    return $this;
  }

  public function getHost() {
    return $this->host;
  }

  public function setPath($path) {
    $this->path = $path;
    return $this;
  }

  public function getPath() {
    return $this->path;
  }


  /**
   * @phutil-external-symbol class PhabricatorStartup
   */
  public static function runHTTPRequest(AphrontHTTPSink $sink) {
    if (isset($_SERVER['HTTP_X_SETUP_SELFCHECK'])) {
      $response = self::newSelfCheckResponse();
      return self::writeResponse($sink, $response);
    }

    PhabricatorStartup::beginStartupPhase('multimeter');
    $multimeter = MultimeterControl::newInstance();
    $multimeter->setEventContext('<http-init>');
    $multimeter->setEventViewer('<none>');

    // Build a no-op write guard for the setup phase. We'll replace this with a
    // real write guard later on, but we need to survive setup and build a
    // request object first.
    $write_guard = new AphrontWriteGuard('id');

    PhabricatorStartup::beginStartupPhase('preflight');

    $response = PhabricatorSetupCheck::willPreflightRequest();
    if ($response) {
      return self::writeResponse($sink, $response);
    }

    PhabricatorStartup::beginStartupPhase('env.init');

    self::readHTTPPOSTData();

    try {
      PhabricatorEnv::initializeWebEnvironment();
      $database_exception = null;
    } catch (PhabricatorClusterStrandedException $ex) {
      $database_exception = $ex;
    }

    // If we're in developer mode, set a flag so that top-level exception
    // handlers can add more information.
    if (PhabricatorEnv::getEnvConfig('phabricator.developer-mode')) {
      $sink->setShowStackTraces(true);
    }

    if ($database_exception) {
      $issue = PhabricatorSetupIssue::newDatabaseConnectionIssue(
        $database_exception,
        true);
      $response = PhabricatorSetupCheck::newIssueResponse($issue);
      return self::writeResponse($sink, $response);
    }

    $multimeter->setSampleRate(
      PhabricatorEnv::getEnvConfig('debug.sample-rate'));

    $debug_time_limit = PhabricatorEnv::getEnvConfig('debug.time-limit');
    if ($debug_time_limit) {
      PhabricatorStartup::setDebugTimeLimit($debug_time_limit);
    }

    // This is the earliest we can get away with this, we need env config first.
    PhabricatorStartup::beginStartupPhase('log.access');
    PhabricatorAccessLog::init();
    $access_log = PhabricatorAccessLog::getLog();
    PhabricatorStartup::setAccessLog($access_log);

    $address = PhabricatorEnv::getRemoteAddress();
    if ($address) {
      $address_string = $address->getAddress();
    } else {
      $address_string = '-';
    }

    $access_log->setData(
      array(
        'R' => AphrontRequest::getHTTPHeader('Referer', '-'),
        'r' => $address_string,
        'M' => idx($_SERVER, 'REQUEST_METHOD', '-'),
      ));

    DarkConsoleXHProfPluginAPI::hookProfiler();

    // We just activated the profiler, so we don't need to keep track of
    // startup phases anymore: it can take over from here.
    PhabricatorStartup::beginStartupPhase('startup.done');

    DarkConsoleErrorLogPluginAPI::registerErrorHandler();

    $response = PhabricatorSetupCheck::willProcessRequest();
    if ($response) {
      return self::writeResponse($sink, $response);
    }

    $host = AphrontRequest::getHTTPHeader('Host');
    $path = PhabricatorStartup::getRequestPath();

    $application = new self();

    $application->setHost($host);
    $application->setPath($path);
    $request = $application->buildRequest();

    // Now that we have a request, convert the write guard into one which
    // actually checks CSRF tokens.
    $write_guard->dispose();
    $write_guard = new AphrontWriteGuard(array($request, 'validateCSRF'));

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

    PhabricatorStartup::disconnectRateLimits(
      array(
        'viewer' => $request->getUser(),
      ));

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

    $request->setController($controller);
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
        $this->validateControllerResponse($controller, $response);
      }
    } catch (Exception $ex) {
      $original_exception = $ex;
    } catch (Throwable $ex) {
      $original_exception = $ex;
    }

    $response_exception = null;
    try {
      if ($original_exception) {
        $response = $this->handleThrowable($original_exception);
      }

      $response = $this->produceResponse($request, $response);
      $response = $controller->willSendResponse($response);
      $response->setRequest($request);

      self::writeResponse($sink, $response);
    } catch (Exception $ex) {
      $response_exception = $ex;
    } catch (Throwable $ex) {
      $response_exception = $ex;
    }

    if ($response_exception) {
      // If we encountered an exception while building a normal response, then
      // encountered another exception while building a response for the first
      // exception, throw an aggregate exception that will be unpacked by the
      // higher-level handler. This is above our pay grade.
      if ($original_exception) {
        throw new PhutilAggregateException(
          pht(
            'Encountered a processing exception, then another exception when '.
            'trying to build a response for the first exception.'),
          array(
            $response_exception,
            $original_exception,
          ));
      }

      // If we built a response successfully and then ran into an exception
      // trying to render it, try to handle and present that exception to the
      // user using the standard handler.

      // The problem here might be in rendering (more common) or in the actual
      // response mechanism (less common). If it's in rendering, we can likely
      // still render a nice exception page: the majority of rendering issues
      // are in main page content, not content shared with the exception page.

      $handling_exception = null;
      try {
        $response = $this->handleThrowable($response_exception);

        $response = $this->produceResponse($request, $response);
        $response = $controller->willSendResponse($response);
        $response->setRequest($request);

        self::writeResponse($sink, $response);
      } catch (Exception $ex) {
        $handling_exception = $ex;
      } catch (Throwable $ex) {
        $handling_exception = $ex;
      }

      // If we didn't have any luck with that, raise the original response
      // exception. As above, this is the root cause exception and more likely
      // to be useful. This will go to the fallback error handler at top
      // level.

      if ($handling_exception) {
        throw $response_exception;
      }
    }

    return $response;
  }

  private static function writeResponse(
    AphrontHTTPSink $sink,
    AphrontResponse $response) {

    $unexpected_output = PhabricatorStartup::endOutputCapture();
    if ($unexpected_output) {
      $unexpected_output = pht(
        "Unexpected output:\n\n%s",
        $unexpected_output);

      phlog($unexpected_output);

      if ($response instanceof AphrontWebpageResponse) {
        $response->setUnexpectedOutput($unexpected_output);
      }
    }

    $sink->writeResponse($response);
  }


/* -(  URI Routing  )-------------------------------------------------------- */


  /**
   * Build a controller to respond to the request.
   *
   * @return pair<AphrontController,dict> Controller and dictionary of request
   *                                      parameters.
   * @task routing
   */
  private function buildController() {
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
          throw new AphrontMalformedRequestException(
            pht('No %s', 'SERVER_ADDR'),
            pht(
              'This service is configured to operate in cluster mode, but '.
              '%s is not defined in the request context. Your webserver '.
              'configuration needs to forward %s to PHP so the software can '.
              'reject requests received on external interfaces.',
              'SERVER_ADDR',
              'SERVER_ADDR'));
        }
      } else {
        if (!PhabricatorEnv::isClusterAddress($server_addr)) {
          throw new AphrontMalformedRequestException(
            pht('External Interface'),
            pht(
              'This service is configured in cluster mode and the address '.
              'this request was received on ("%s") is not whitelisted as '.
              'a cluster address.',
              $server_addr));
        }
      }
    }

    $site = $this->buildSiteForRequest($request);

    if ($site->shouldRequireHTTPS()) {
      if (!$request->isHTTPS()) {

        // Don't redirect intracluster requests: doing so drops headers and
        // parameters, imposes a performance penalty, and indicates a
        // misconfiguration.
        if ($request->isProxiedClusterRequest()) {
          throw new AphrontMalformedRequestException(
            pht('HTTPS Required'),
            pht(
              'This request reached a site which requires HTTPS, but the '.
              'request is not marked as HTTPS.'));
        }

        $https_uri = $request->getRequestURI();
        $https_uri->setDomain($request->getHost());
        $https_uri->setProtocol('https');

        // In this scenario, we'll be redirecting to HTTPS using an absolute
        // URI, so we need to permit an external redirect.
        return $this->buildRedirectController($https_uri, true);
      }
    }

    $maps = $site->getRoutingMaps();
    $path = $request->getPath();

    $result = $this->routePath($maps, $path);
    if ($result) {
      return $result;
    }

    // If we failed to match anything but don't have a trailing slash, try
    // to add a trailing slash and issue a redirect if that resolves.

    // NOTE: We only do this for GET, since redirects switch to GET and drop
    // data like POST parameters.
    if (!preg_match('@/$@', $path) && $request->isHTTPGet()) {
      $result = $this->routePath($maps, $path.'/');
      if ($result) {
        $target_uri = $request->getAbsoluteRequestURI();

        // We need to restore URI encoding because the webserver has
        // interpreted it. For example, this allows us to redirect a path
        // like `/tag/aa%20bb` to `/tag/aa%20bb/`, which may eventually be
        // resolved meaningfully by an application.
        $target_path = phutil_escape_uri($path.'/');
        $target_uri->setPath($target_path);
        $target_uri = (string)$target_uri;

        return $this->buildRedirectController($target_uri, true);
      }
    }

    $result = $site->new404Controller($request);
    if ($result) {
      return array($result, array());
    }

    throw new Exception(
      pht(
        'Aphront site ("%s") failed to build a 404 controller.',
        get_class($site)));
  }

  /**
   * Map a specific path to the corresponding controller. For a description
   * of routing, see @{method:buildController}.
   *
   * @param list<AphrontRoutingMap> List of routing maps.
   * @param string Path to route.
   * @return pair<AphrontController,dict> Controller and dictionary of request
   *                                      parameters.
   * @task routing
   */
  private function routePath(array $maps, $path) {
    foreach ($maps as $map) {
      $result = $map->routePath($path);
      if ($result) {
        return array($result->getController(), $result->getURIData());
      }
    }
  }

  private function buildSiteForRequest(AphrontRequest $request) {
    $sites = PhabricatorSite::getAllSites();

    $site = null;
    foreach ($sites as $candidate) {
      $site = $candidate->newSiteForRequest($request);
      if ($site) {
        break;
      }
    }

    if (!$site) {
      $path = $request->getPath();
      $host = $request->getHost();
      throw new AphrontMalformedRequestException(
        pht('Site Not Found'),
        pht(
          'This request asked for "%s" on host "%s", but no site is '.
          'configured which can serve this request.',
          $path,
          $host),
        true);
    }

    $request->setSite($site);

    return $site;
  }


/* -(  Response Handling  )-------------------------------------------------- */


  /**
   * Tests if a response is of a valid type.
   *
   * @param wild Supposedly valid response.
   * @return bool True if the object is of a valid type.
   * @task response
   */
  private function isValidResponseObject($response) {
    if ($response instanceof AphrontResponse) {
      return true;
    }

    if ($response instanceof AphrontResponseProducerInterface) {
      return true;
    }

    return false;
  }


  /**
   * Verifies that the return value from an @{class:AphrontController} is
   * of an allowed type.
   *
   * @param AphrontController Controller which returned the response.
   * @param wild Supposedly valid response.
   * @return void
   * @task response
   */
  private function validateControllerResponse(
    AphrontController $controller,
    $response) {

    if ($this->isValidResponseObject($response)) {
      return;
    }

    throw new Exception(
      pht(
        'Controller "%s" returned an invalid response from call to "%s". '.
        'This method must return an object of class "%s", or an object '.
        'which implements the "%s" interface.',
        get_class($controller),
        'handleRequest()',
        'AphrontResponse',
        'AphrontResponseProducerInterface'));
  }


  /**
   * Verifies that the return value from an
   * @{class:AphrontResponseProducerInterface} is of an allowed type.
   *
   * @param AphrontResponseProducerInterface Object which produced
   *   this response.
   * @param wild Supposedly valid response.
   * @return void
   * @task response
   */
  private function validateProducerResponse(
    AphrontResponseProducerInterface $producer,
    $response) {

    if ($this->isValidResponseObject($response)) {
      return;
    }

    throw new Exception(
      pht(
        'Producer "%s" returned an invalid response from call to "%s". '.
        'This method must return an object of class "%s", or an object '.
        'which implements the "%s" interface.',
        get_class($producer),
        'produceAphrontResponse()',
        'AphrontResponse',
        'AphrontResponseProducerInterface'));
  }


  /**
   * Verifies that the return value from an
   * @{class:AphrontRequestExceptionHandler} is of an allowed type.
   *
   * @param AphrontRequestExceptionHandler Object which produced this
   *  response.
   * @param wild Supposedly valid response.
   * @return void
   * @task response
   */
  private function validateErrorHandlerResponse(
    AphrontRequestExceptionHandler $handler,
    $response) {

    if ($this->isValidResponseObject($response)) {
      return;
    }

    throw new Exception(
      pht(
        'Exception handler "%s" returned an invalid response from call to '.
        '"%s". This method must return an object of class "%s", or an object '.
        'which implements the "%s" interface.',
        get_class($handler),
        'handleRequestException()',
        'AphrontResponse',
        'AphrontResponseProducerInterface'));
  }


  /**
   * Resolves a response object into an @{class:AphrontResponse}.
   *
   * Controllers are permitted to return actual responses of class
   * @{class:AphrontResponse}, or other objects which implement
   * @{interface:AphrontResponseProducerInterface} and can produce a response.
   *
   * If a controller returns a response producer, invoke it now and produce
   * the real response.
   *
   * @param AphrontRequest Request being handled.
   * @param AphrontResponse|AphrontResponseProducerInterface Response, or
   *   response producer.
   * @return AphrontResponse Response after any required production.
   * @task response
   */
  private function produceResponse(AphrontRequest $request, $response) {
    $original = $response;

    // Detect cycles on the exact same objects. It's still possible to produce
    // infinite responses as long as they're all unique, but we can only
    // reasonably detect cycles, not guarantee that response production halts.

    $seen = array();
    while (true) {
      // NOTE: It is permissible for an object to be both a response and a
      // response producer. If so, being a producer is "stronger". This is
      // used by AphrontProxyResponse.

      // If this response is a valid response, hand over the request first.
      if ($response instanceof AphrontResponse) {
        $response->setRequest($request);
      }

      // If this isn't a producer, we're all done.
      if (!($response instanceof AphrontResponseProducerInterface)) {
        break;
      }

      $hash = spl_object_hash($response);
      if (isset($seen[$hash])) {
        throw new Exception(
          pht(
            'Failure while producing response for object of class "%s": '.
            'encountered production cycle (identical object, of class "%s", '.
            'was produced twice).',
            get_class($original),
            get_class($response)));
      }

      $seen[$hash] = true;

      $new_response = $response->produceAphrontResponse();
      $this->validateProducerResponse($response, $new_response);
      $response = $new_response;
    }

    return $response;
  }


/* -(  Error Handling  )----------------------------------------------------- */


  /**
   * Convert an exception which has escaped the controller into a response.
   *
   * This method delegates exception handling to available subclasses of
   * @{class:AphrontRequestExceptionHandler}.
   *
   * @param Throwable Exception which needs to be handled.
   * @return wild Response or response producer, or null if no available
   *   handler can produce a response.
   * @task exception
   */
  private function handleThrowable($throwable) {
    $handlers = AphrontRequestExceptionHandler::getAllHandlers();

    $request = $this->getRequest();
    foreach ($handlers as $handler) {
      if ($handler->canHandleRequestThrowable($request, $throwable)) {
        $response = $handler->handleRequestThrowable($request, $throwable);
        $this->validateErrorHandlerResponse($handler, $response);
        return $response;
      }
    }

    throw $throwable;
  }

  private static function newSelfCheckResponse() {
    $path = PhabricatorStartup::getRequestPath();
    $query = idx($_SERVER, 'QUERY_STRING', '');

    $pairs = id(new PhutilQueryStringParser())
      ->parseQueryStringToPairList($query);

    $params = array();
    foreach ($pairs as $v) {
      $params[] = array(
        'name' => $v[0],
        'value' => $v[1],
      );
    }

    $raw_input = @file_get_contents('php://input');
    if ($raw_input !== false) {
      $base64_input = base64_encode($raw_input);
    } else {
      $base64_input = null;
    }

    $result = array(
      'path' => $path,
      'params' => $params,
      'user' => idx($_SERVER, 'PHP_AUTH_USER'),
      'pass' => idx($_SERVER, 'PHP_AUTH_PW'),

      'raw.base64' => $base64_input,

      // This just makes sure that the response compresses well, so reasonable
      // algorithms should want to gzip or deflate it.
      'filler' => str_repeat('Q', 1024 * 16),
    );

    return id(new AphrontJSONResponse())
      ->setAddJSONShield(false)
      ->setContent($result);
  }

  private static function readHTTPPOSTData() {
    $request_method = idx($_SERVER, 'REQUEST_METHOD');
    if ($request_method === 'PUT') {
      // For PUT requests, do nothing: in particular, do NOT read input. This
      // allows us to stream input later and process very large PUT requests,
      // like those coming from Git LFS.
      return;
    }


    // For POST requests, we're going to read the raw input ourselves here
    // if we can. Among other things, this corrects variable names with
    // the "." character in them, which PHP normally converts into "_".

    // If "enable_post_data_reading" is on, the documentation suggests we
    // can not read the body. In practice, we seem to be able to. This may
    // need to be resolved at some point, likely by instructing installs
    // to disable this option.

    // If the content type is "multipart/form-data", we need to build both
    // $_POST and $_FILES, which is involved. The body itself is also more
    // difficult to parse than other requests.

    $raw_input = PhabricatorStartup::getRawInput();
    $parser = new PhutilQueryStringParser();

    if (strlen($raw_input)) {
      $content_type = idx($_SERVER, 'CONTENT_TYPE');
      $is_multipart = preg_match('@^multipart/form-data@i', $content_type);
      if ($is_multipart) {
        $multipart_parser = id(new AphrontMultipartParser())
          ->setContentType($content_type);

        $multipart_parser->beginParse();
        $multipart_parser->continueParse($raw_input);
        $parts = $multipart_parser->endParse();

        // We're building and then parsing a query string so that requests
        // with arrays (like "x[]=apple&x[]=banana") work correctly. This also
        // means we can't use "phutil_build_http_querystring()", since it
        // can't build a query string with duplicate names.

        $query_string = array();
        foreach ($parts as $part) {
          if (!$part->isVariable()) {
            continue;
          }

          $name = $part->getName();
          $value = $part->getVariableValue();
          $query_string[] = rawurlencode($name).'='.rawurlencode($value);
        }
        $query_string = implode('&', $query_string);
        $post = $parser->parseQueryString($query_string);

        $files = array();
        foreach ($parts as $part) {
          if ($part->isVariable()) {
            continue;
          }

          $files[$part->getName()] = $part->getPHPFileDictionary();
        }
        $_FILES = $files;
      } else {
        $post = $parser->parseQueryString($raw_input);
      }

      $_POST = $post;
      PhabricatorStartup::rebuildRequest();
    } else if ($_POST) {
      $post = filter_input_array(INPUT_POST, FILTER_UNSAFE_RAW);
      if (is_array($post)) {
        $_POST = $post;
        PhabricatorStartup::rebuildRequest();
      }
    }
  }

}
