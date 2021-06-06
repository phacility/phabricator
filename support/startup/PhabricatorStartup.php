<?php

/**
 * Handle request startup, before loading the environment or libraries. This
 * class bootstraps the request state up to the point where we can enter
 * Phabricator code.
 *
 * NOTE: This class MUST NOT have any dependencies. It runs before libraries
 * load.
 *
 * Rate Limiting
 * =============
 *
 * Phabricator limits the rate at which clients can request pages, and issues
 * HTTP 429 "Too Many Requests" responses if clients request too many pages too
 * quickly. Although this is not a complete defense against high-volume attacks,
 * it can  protect an install against aggressive crawlers, security scanners,
 * and some types of malicious activity.
 *
 * To perform rate limiting, each page increments a score counter for the
 * requesting user's IP. The page can give the IP more points for an expensive
 * request, or fewer for an authetnicated request.
 *
 * Score counters are kept in buckets, and writes move to a new bucket every
 * minute. After a few minutes (defined by @{method:getRateLimitBucketCount}),
 * the oldest bucket is discarded. This provides a simple mechanism for keeping
 * track of scores without needing to store, access, or read very much data.
 *
 * Users are allowed to accumulate up to 1000 points per minute, averaged across
 * all of the tracked buckets.
 *
 * @task info         Accessing Request Information
 * @task hook         Startup Hooks
 * @task apocalypse   In Case Of Apocalypse
 * @task validation   Validation
 * @task ratelimit    Rate Limiting
 * @task phases       Startup Phase Timers
 * @task request-path Request Path
 */
final class PhabricatorStartup {

  private static $startTime;
  private static $debugTimeLimit;
  private static $accessLog;
  private static $capturingOutput;
  private static $rawInput;
  private static $oldMemoryLimit;
  private static $phases;

  private static $limits = array();
  private static $requestPath;


/* -(  Accessing Request Information  )-------------------------------------- */


  /**
   * @task info
   */
  public static function getStartTime() {
    return self::$startTime;
  }


  /**
   * @task info
   */
  public static function getMicrosecondsSinceStart() {
    // This is the same as "phutil_microseconds_since()", but we may not have
    // loaded libraries yet.
    return (int)(1000000 * (microtime(true) - self::getStartTime()));
  }


  /**
   * @task info
   */
  public static function setAccessLog($access_log) {
    self::$accessLog = $access_log;
  }


  /**
   * @task info
   */
  public static function getRawInput() {
    if (self::$rawInput === null) {
      $stream = new AphrontRequestStream();

      if (isset($_SERVER['HTTP_CONTENT_ENCODING'])) {
        $encoding = trim($_SERVER['HTTP_CONTENT_ENCODING']);
        $stream->setEncoding($encoding);
      }

      $input = '';
      do {
        $bytes = $stream->readData();
        if ($bytes === null) {
          break;
        }
        $input .= $bytes;
      } while (true);

      self::$rawInput = $input;
    }

    return self::$rawInput;
  }


/* -(  Startup Hooks  )------------------------------------------------------ */


  /**
   * @param float Request start time, from `microtime(true)`.
   * @task hook
   */
  public static function didStartup($start_time) {
    self::$startTime = $start_time;

    self::$phases = array();

    self::$accessLog = null;
    self::$requestPath = null;

    static $registered;
    if (!$registered) {
      // NOTE: This protects us against multiple calls to didStartup() in the
      // same request, but also against repeated requests to the same
      // interpreter state, which we may implement in the future.
      register_shutdown_function(array(__CLASS__, 'didShutdown'));
      $registered = true;
    }

    self::setupPHP();
    self::verifyPHP();

    // If we've made it this far, the environment isn't completely broken so
    // we can switch over to relying on our own exception recovery mechanisms.
    ini_set('display_errors', 0);

    self::connectRateLimits();

    self::normalizeInput();

    self::readRequestPath();

    self::beginOutputCapture();
  }


  /**
   * @task hook
   */
  public static function didShutdown() {
    // Disconnect any active rate limits before we shut down. If we don't do
    // this, requests which exit early will lock a slot in any active
    // connection limits, and won't count for rate limits.
    self::disconnectRateLimits(array());

    $event = error_get_last();

    if (!$event) {
      return;
    }

    switch ($event['type']) {
      case E_ERROR:
      case E_PARSE:
      case E_COMPILE_ERROR:
        break;
      default:
        return;
    }

    $msg = ">>> UNRECOVERABLE FATAL ERROR <<<\n\n";
    if ($event) {
      // Even though we should be emitting this as text-plain, escape things
      // just to be sure since we can't really be sure what the program state
      // is when we get here.
      $msg .= htmlspecialchars(
        $event['message']."\n\n".$event['file'].':'.$event['line'],
        ENT_QUOTES,
        'UTF-8');
    }

    // flip dem tables
    $msg .= "\n\n\n";
    $msg .= "\xe2\x94\xbb\xe2\x94\x81\xe2\x94\xbb\x20\xef\xb8\xb5\x20\xc2\xaf".
            "\x5c\x5f\x28\xe3\x83\x84\x29\x5f\x2f\xc2\xaf\x20\xef\xb8\xb5\x20".
            "\xe2\x94\xbb\xe2\x94\x81\xe2\x94\xbb";

    self::didFatal($msg);
  }

  public static function loadCoreLibraries() {
    $phabricator_root = dirname(dirname(dirname(__FILE__)));
    $libraries_root = dirname($phabricator_root);

    $root = null;
    if (!empty($_SERVER['PHUTIL_LIBRARY_ROOT'])) {
      $root = $_SERVER['PHUTIL_LIBRARY_ROOT'];
    }

    ini_set(
      'include_path',
      $libraries_root.PATH_SEPARATOR.ini_get('include_path'));

    $ok = @include_once $root.'arcanist/src/init/init-library.php';
    if (!$ok) {
      self::didFatal(
        'Unable to load the "Arcanist" library. Put "arcanist/" next to '.
        '"phabricator/" on disk.');
    }

    // Load Phabricator itself using the absolute path, so we never end up doing
    // anything surprising (loading index.php and libraries from different
    // directories).
    phutil_load_library($phabricator_root.'/src');
  }

/* -(  Output Capture  )----------------------------------------------------- */


  public static function beginOutputCapture() {
    if (self::$capturingOutput) {
      self::didFatal('Already capturing output!');
    }
    self::$capturingOutput = true;
    ob_start();
  }


  public static function endOutputCapture() {
    if (!self::$capturingOutput) {
      return null;
    }
    self::$capturingOutput = false;
    return ob_get_clean();
  }


/* -(  Debug Time Limit  )--------------------------------------------------- */


  /**
   * Set a time limit (in seconds) for the current script. After time expires,
   * the script fatals.
   *
   * This works like `max_execution_time`, but prints out a useful stack trace
   * when the time limit expires. This is primarily intended to make it easier
   * to debug pages which hang by allowing extraction of a stack trace: set a
   * short debug limit, then use the trace to figure out what's happening.
   *
   * The limit is implemented with a tick function, so enabling it implies
   * some accounting overhead.
   *
   * @param int Time limit in seconds.
   * @return void
   */
  public static function setDebugTimeLimit($limit) {
    self::$debugTimeLimit = $limit;

    static $initialized;
    if (!$initialized) {
      declare(ticks=1);
      register_tick_function(array(__CLASS__, 'onDebugTick'));
    }
  }


  /**
   * Callback tick function used by @{method:setDebugTimeLimit}.
   *
   * Fatals with a useful stack trace after the time limit expires.
   *
   * @return void
   */
  public static function onDebugTick() {
    $limit = self::$debugTimeLimit;
    if (!$limit) {
      return;
    }

    $elapsed = (microtime(true) - self::getStartTime());
    if ($elapsed > $limit) {
      $frames = array();
      foreach (debug_backtrace() as $frame) {
        $file = isset($frame['file']) ? $frame['file'] : '-';
        $file = basename($file);

        $line = isset($frame['line']) ? $frame['line'] : '-';
        $class = isset($frame['class']) ? $frame['class'].'->' : null;
        $func = isset($frame['function']) ? $frame['function'].'()' : '?';

        $frames[] = "{$file}:{$line} {$class}{$func}";
      }

      self::didFatal(
        "Request aborted by debug time limit after {$limit} seconds.\n\n".
        "STACK TRACE\n".
        implode("\n", $frames));
    }
  }


/* -(  In Case of Apocalypse  )---------------------------------------------- */


  /**
   * Fatal the request completely in response to an exception, sending a plain
   * text message to the client. Calls @{method:didFatal} internally.
   *
   * @param   string    Brief description of the exception context, like
   *                    `"Rendering Exception"`.
   * @param   Throwable The exception itself.
   * @param   bool      True if it's okay to show the exception's stack trace
   *                    to the user. The trace will always be logged.
   * @return  exit      This method **does not return**.
   *
   * @task apocalypse
   */
  public static function didEncounterFatalException(
    $note,
    $ex,
    $show_trace) {

    $message = '['.$note.'/'.get_class($ex).'] '.$ex->getMessage();

    $full_message = $message;
    $full_message .= "\n\n";
    $full_message .= $ex->getTraceAsString();

    if ($show_trace) {
      $message = $full_message;
    }

    self::didFatal($message, $full_message);
  }


  /**
   * Fatal the request completely, sending a plain text message to the client.
   *
   * @param   string  Plain text message to send to the client.
   * @param   string  Plain text message to send to the error log. If not
   *                  provided, the client message is used. You can pass a more
   *                  detailed message here (e.g., with stack traces) to avoid
   *                  showing it to users.
   * @return  exit    This method **does not return**.
   *
   * @task apocalypse
   */
  public static function didFatal($message, $log_message = null) {
    if ($log_message === null) {
      $log_message = $message;
    }

    self::endOutputCapture();
    $access_log = self::$accessLog;
    if ($access_log) {
      // We may end up here before the access log is initialized, e.g. from
      // verifyPHP().
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

    error_log($log_message);
    echo $message."\n";

    exit(1);
  }


/* -(  Validation  )--------------------------------------------------------- */


  /**
   * @task validation
   */
  private static function setupPHP() {
    error_reporting(E_ALL | E_STRICT);
    self::$oldMemoryLimit = ini_get('memory_limit');
    ini_set('memory_limit', -1);

    // If we have libxml, disable the incredibly dangerous entity loader.
    // PHP 8 deprecates this function and disables this by default; remove once
    // PHP 7 is no longer supported or a future version has removed the function
    // entirely.
    if (function_exists('libxml_disable_entity_loader')) {
      @libxml_disable_entity_loader(true);
    }

    // See T13060. If the locale for this process (the parent process) is not
    // a UTF-8 locale we can encounter problems when launching subprocesses
    // which receive UTF-8 parameters in their command line argument list.
    @setlocale(LC_ALL, 'en_US.UTF-8');

    $config_map = array(
      // See PHI1894. Keep "args" in exception backtraces.
      'zend.exception_ignore_args' => 0,

      // See T13100. We'd like the regex engine to fail, rather than segfault,
      // if handed a pathological regular expression.
      'pcre.backtrack_limit' => 10000,
      'pcre.recusion_limit' => 10000,

      // NOTE: Arcanist applies a similar set of startup options for CLI
      // environments in "init-script.php". Changes here may also be
      // appropriate to apply there.
    );

    foreach ($config_map as $config_key => $config_value) {
      ini_set($config_key, $config_value);
    }
  }


  /**
   * @task validation
   */
  public static function getOldMemoryLimit() {
    return self::$oldMemoryLimit;
  }

  /**
   * @task validation
   */
  private static function normalizeInput() {
    // Replace superglobals with unfiltered versions, disrespect php.ini (we
    // filter ourselves).

    // NOTE: We don't filter INPUT_SERVER because we don't want to overwrite
    // changes made in "preamble.php".

    // NOTE: WE don't filter INPUT_POST because we may be constructing it
    // lazily if "enable_post_data_reading" is disabled.

    $filter = array(
      INPUT_GET,
      INPUT_ENV,
      INPUT_COOKIE,
    );
    foreach ($filter as $type) {
      $filtered = filter_input_array($type, FILTER_UNSAFE_RAW);
      if (!is_array($filtered)) {
        continue;
      }
      switch ($type) {
        case INPUT_GET:
          $_GET = array_merge($_GET, $filtered);
          break;
        case INPUT_COOKIE:
          $_COOKIE = array_merge($_COOKIE, $filtered);
          break;
        case INPUT_ENV;
          $env = array_merge($_ENV, $filtered);
          $_ENV = self::filterEnvSuperglobal($env);
          break;
      }
    }

    self::rebuildRequest();
  }

  /**
   * @task validation
   */
  public static function rebuildRequest() {
    // Rebuild $_REQUEST, respecting order declared in ".ini" files.
    $order = ini_get('request_order');

    if (!$order) {
      $order = ini_get('variables_order');
    }

    if (!$order) {
      // $_REQUEST will be empty, so leave it alone.
      return;
    }

    $_REQUEST = array();
    for ($ii = 0; $ii < strlen($order); $ii++) {
      switch ($order[$ii]) {
        case 'G':
          $_REQUEST = array_merge($_REQUEST, $_GET);
          break;
        case 'P':
          $_REQUEST = array_merge($_REQUEST, $_POST);
          break;
        case 'C':
          $_REQUEST = array_merge($_REQUEST, $_COOKIE);
          break;
        default:
          // $_ENV and $_SERVER never go into $_REQUEST.
          break;
      }
    }
  }


  /**
   * Adjust `$_ENV` before execution.
   *
   * Adjustments here primarily impact the environment as seen by subprocesses.
   * The environment is forwarded explicitly by @{class:ExecFuture}.
   *
   * @param map<string, wild> Input `$_ENV`.
   * @return map<string, string> Suitable `$_ENV`.
   * @task validation
   */
  private static function filterEnvSuperglobal(array $env) {

    // In some configurations, we may get "argc" and "argv" set in $_ENV.
    // These are not real environmental variables, and "argv" may have an array
    // value which can not be forwarded to subprocesses. Remove these from the
    // environment if they are present.
    unset($env['argc']);
    unset($env['argv']);

    return $env;
  }


  /**
   * @task validation
   */
  private static function verifyPHP() {
    $required_version = '5.2.3';
    if (version_compare(PHP_VERSION, $required_version) < 0) {
      self::didFatal(
        "You are running PHP version '".PHP_VERSION."', which is older than ".
        "the minimum version, '{$required_version}'. Update to at least ".
        "'{$required_version}'.");
    }

    if (function_exists('get_magic_quotes_gpc')) {
      if (@get_magic_quotes_gpc()) {
        self::didFatal(
          'Your server is configured with the PHP language feature '.
          '"magic_quotes_gpc" enabled.'.
          "\n\n".
          'This feature is "highly discouraged" by PHP\'s developers, and '.
          'has been removed entirely in PHP8.'.
          "\n\n".
          'You must disable "magic_quotes_gpc" to run Phabricator. Consult '.
          'the PHP manual for instructions.');
      }
    }

    if (extension_loaded('apc')) {
      $apc_version = phpversion('apc');
      $known_bad = array(
        '3.1.14' => true,
        '3.1.15' => true,
        '3.1.15-dev' => true,
      );
      if (isset($known_bad[$apc_version])) {
        self::didFatal(
          "You have APC {$apc_version} installed. This version of APC is ".
          "known to be bad, and does not work with Phabricator (it will ".
          "cause Phabricator to fatal unrecoverably with nonsense errors). ".
          "Downgrade to version 3.1.13.");
      }
    }

    if (isset($_SERVER['HTTP_PROXY'])) {
      self::didFatal(
        'This HTTP request included a "Proxy:" header, poisoning the '.
        'environment (CVE-2016-5385 / httpoxy). Declining to process this '.
        'request. For details, see: https://phurl.io/u/httpoxy');
    }
  }


  /**
   * @task request-path
   */
  private static function readRequestPath() {

    // See T13575. The request path may be provided in:
    //
    //  - the "$_GET" parameter "__path__" (normal for Apache and nginx); or
    //  - the "$_SERVER" parameter "REQUEST_URI" (normal for the PHP builtin
    //    webserver).
    //
    // Locate it wherever it is, and store it for later use. Note that writing
    // to "$_REQUEST" here won't always work, because later code may rebuild
    // "$_REQUEST" from other sources.

    if (isset($_REQUEST['__path__']) && strlen($_REQUEST['__path__'])) {
      self::setRequestPath($_REQUEST['__path__']);
      return;
    }

    // Compatibility with PHP 5.4+ built-in web server.
    if (php_sapi_name() == 'cli-server') {
      $path = parse_url($_SERVER['REQUEST_URI']);
      self::setRequestPath($path['path']);
      return;
    }

    if (!isset($_REQUEST['__path__'])) {
      self::didFatal(
        "Request parameter '__path__' is not set. Your rewrite rules ".
        "are not configured correctly.");
    }

    if (!strlen($_REQUEST['__path__'])) {
      self::didFatal(
        "Request parameter '__path__' is set, but empty. Your rewrite rules ".
        "are not configured correctly. The '__path__' should always ".
        "begin with a '/'.");
    }
  }

  /**
   * @task request-path
   */
  public static function getRequestPath() {
    $path = self::$requestPath;

    if ($path === null) {
      self::didFatal(
        'Request attempted to access request path, but no request path is '.
        'available for this request. You may be calling web request code '.
        'from a non-request context, or your webserver may not be passing '.
        'a request path to Phabricator in a format that it understands.');
    }

    return $path;
  }

  /**
   * @task request-path
   */
  public static function setRequestPath($path) {
    self::$requestPath = $path;
  }


/* -(  Rate Limiting  )------------------------------------------------------ */


  /**
   * Add a new client limits.
   *
   * @param PhabricatorClientLimit New limit.
   * @return PhabricatorClientLimit The limit.
   */
  public static function addRateLimit(PhabricatorClientLimit $limit) {
    self::$limits[] = $limit;
    return $limit;
  }


  /**
   * Apply configured rate limits.
   *
   * If any limit is exceeded, this method terminates the request.
   *
   * @return void
   * @task ratelimit
   */
  private static function connectRateLimits() {
    $limits = self::$limits;

    $reason = null;
    $connected = array();
    foreach ($limits as $limit) {
      $reason = $limit->didConnect();
      $connected[] = $limit;
      if ($reason !== null) {
        break;
      }
    }

    // If we're killing the request here, disconnect any limits that we
    // connected to try to keep the accounting straight.
    if ($reason !== null) {
      foreach ($connected as $limit) {
        $limit->didDisconnect(array());
      }

      self::didRateLimit($reason);
    }
  }


  /**
   * Tear down rate limiting and allow limits to score the request.
   *
   * @param map<string, wild> Additional, freeform request state.
   * @return void
   * @task ratelimit
   */
  public static function disconnectRateLimits(array $request_state) {
    $limits = self::$limits;

    // Remove all limits before disconnecting them so this works properly if
    // it runs twice. (We run this automatically as a shutdown handler.)
    self::$limits = array();

    foreach ($limits as $limit) {
      $limit->didDisconnect($request_state);
    }
  }



  /**
   * Emit an HTTP 429 "Too Many Requests" response (indicating that the user
   * has exceeded application rate limits) and exit.
   *
   * @return exit This method **does not return**.
   * @task ratelimit
   */
  private static function didRateLimit($reason) {
    header(
      'Content-Type: text/plain; charset=utf-8',
      $replace = true,
      $http_error = 429);

    echo $reason;

    exit(1);
  }


/* -(  Startup Timers  )----------------------------------------------------- */


  /**
   * Record the beginning of a new startup phase.
   *
   * For phases which occur before @{class:PhabricatorStartup} loads, save the
   * time and record it with @{method:recordStartupPhase} after the class is
   * available.
   *
   * @param string Phase name.
   * @task phases
   */
  public static function beginStartupPhase($phase) {
    self::recordStartupPhase($phase, microtime(true));
  }


  /**
   * Record the start time of a previously executed startup phase.
   *
   * For startup phases which occur after @{class:PhabricatorStartup} loads,
   * use @{method:beginStartupPhase} instead. This method can be used to
   * record a time before the class loads, then hand it over once the class
   * becomes available.
   *
   * @param string Phase name.
   * @param float Phase start time, from `microtime(true)`.
   * @task phases
   */
  public static function recordStartupPhase($phase, $time) {
    self::$phases[$phase] = $time;
  }


  /**
   * Get information about startup phase timings.
   *
   * Sometimes, performance problems can occur before we start the profiler.
   * Since the profiler can't examine these phases, it isn't useful in
   * understanding their performance costs.
   *
   * Instead, the startup process marks when it enters various phases using
   * @{method:beginStartupPhase}. A later call to this method can retrieve this
   * information, which can be examined to gain greater insight into where
   * time was spent. The output is still crude, but better than nothing.
   *
   * @task phases
   */
  public static function getPhases() {
    return self::$phases;
  }

}
