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
 */
final class PhabricatorStartup {

  private static $startTime;
  private static $debugTimeLimit;
  private static $accessLog;
  private static $capturingOutput;
  private static $rawInput;
  private static $oldMemoryLimit;
  private static $phases;

  // TODO: For now, disable rate limiting entirely by default. We need to
  // iterate on it a bit for Conduit, some of the specific score levels, and
  // to deal with NAT'd offices.
  private static $maximumRate = 0;


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

    if (isset($_SERVER['REMOTE_ADDR'])) {
      self::rateLimitRequest($_SERVER['REMOTE_ADDR']);
    }

    self::normalizeInput();

    self::verifyRewriteRules();

    self::detectPostMaxSizeTriggered();

    self::beginOutputCapture();

    self::$rawInput = (string)file_get_contents('php://input');
  }


  /**
   * @task hook
   */
  public static function didShutdown() {
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
    $phabricator_root = dirname(dirname(__FILE__));
    $libraries_root = dirname($phabricator_root);

    $root = null;
    if (!empty($_SERVER['PHUTIL_LIBRARY_ROOT'])) {
      $root = $_SERVER['PHUTIL_LIBRARY_ROOT'];
    }

    ini_set(
      'include_path',
      $libraries_root.PATH_SEPARATOR.ini_get('include_path'));

    @include_once $root.'libphutil/src/__phutil_library_init__.php';
    if (!@constant('__LIBPHUTIL__')) {
      self::didFatal(
        "Unable to load libphutil. Put libphutil/ next to phabricator/, or ".
        "update your PHP 'include_path' to include the parent directory of ".
        "libphutil/.");
    }

    phutil_load_library('arcanist/src');

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
   * @param   Exception The exception itself.
   * @param   bool      True if it's okay to show the exception's stack trace
   *                    to the user. The trace will always be logged.
   * @return  exit      This method **does not return**.
   *
   * @task apocalypse
   */
  public static function didEncounterFatalException(
    $note,
    Exception $ex,
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
    echo $message;

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
    if (function_exists('libxml_disable_entity_loader')) {
      libxml_disable_entity_loader(true);
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
    $filter = array(
      INPUT_GET,
      INPUT_POST,
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
        case INPUT_POST:
          $_POST = array_merge($_POST, $filtered);
          break;
        case INPUT_ENV;
          $env = array_merge($_ENV, $filtered);
          $_ENV = self::filterEnvSuperglobal($env);
          break;
      }
    }

    // rebuild $_REQUEST, respecting order declared in ini files
    $order = ini_get('request_order');
    if (!$order) {
      $order = ini_get('variables_order');
    }
    if (!$order) {
      // $_REQUEST will be empty, leave it alone
      return;
    }
    $_REQUEST = array();
    for ($i = 0; $i < strlen($order); $i++) {
      switch ($order[$i]) {
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
          // $_ENV and $_SERVER never go into $_REQUEST
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

    if (get_magic_quotes_gpc()) {
      self::didFatal(
        "Your server is configured with PHP 'magic_quotes_gpc' enabled. This ".
        "feature is 'highly discouraged' by PHP's developers and you must ".
        "disable it to run Phabricator. Consult the PHP manual for ".
        "instructions.");
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
  }


  /**
   * @task validation
   */
  private static function verifyRewriteRules() {
    if (isset($_REQUEST['__path__']) && strlen($_REQUEST['__path__'])) {
      return;
    }

    if (php_sapi_name() == 'cli-server') {
      // Compatibility with PHP 5.4+ built-in web server.
      $url = parse_url($_SERVER['REQUEST_URI']);
      $_REQUEST['__path__'] = $url['path'];
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
   * Detect if this request has had its POST data stripped by exceeding the
   * 'post_max_size' PHP configuration limit.
   *
   * PHP has a setting called 'post_max_size'. If a POST request arrives with
   * a body larger than the limit, PHP doesn't generate $_POST but processes
   * the request anyway, and provides no formal way to detect that this
   * happened.
   *
   * We can still read the entire body out of `php://input`. However according
   * to the documentation the stream isn't available for "multipart/form-data"
   * (on nginx + php-fpm it appears that it is available, though, at least) so
   * any attempt to generate $_POST would be fragile.
   *
   * @task validation
   */
  private static function detectPostMaxSizeTriggered() {
    // If this wasn't a POST, we're fine.
    if ($_SERVER['REQUEST_METHOD'] != 'POST') {
      return;
    }

    // If there's POST data, clearly we're in good shape.
    if ($_POST) {
      return;
    }

    // For HTML5 drag-and-drop file uploads, Safari submits the data as
    // "application/x-www-form-urlencoded". For most files this generates
    // something in POST because most files decode to some nonempty (albeit
    // meaningless) value. However, some files (particularly small images)
    // don't decode to anything. If we know this is a drag-and-drop upload,
    // we can skip this check.
    if (isset($_REQUEST['__upload__'])) {
      return;
    }

    // PHP generates $_POST only for two content types. This routing happens
    // in `main/php_content_types.c` in PHP. Normally, all forms use one of
    // these content types, but some requests may not -- for example, Firefox
    // submits files sent over HTML5 XMLHTTPRequest APIs with the Content-Type
    // of the file itself. If we don't have a recognized content type, we
    // don't need $_POST.
    //
    // NOTE: We use strncmp() because the actual content type may be something
    // like "multipart/form-data; boundary=...".
    //
    // NOTE: Chrome sometimes omits this header, see some discussion in T1762
    // and http://code.google.com/p/chromium/issues/detail?id=6800
    $content_type = isset($_SERVER['CONTENT_TYPE'])
      ? $_SERVER['CONTENT_TYPE']
      : '';

    $parsed_types = array(
      'application/x-www-form-urlencoded',
      'multipart/form-data',
    );

    $is_parsed_type = false;
    foreach ($parsed_types as $parsed_type) {
      if (strncmp($content_type, $parsed_type, strlen($parsed_type)) === 0) {
        $is_parsed_type = true;
        break;
      }
    }

    if (!$is_parsed_type) {
      return;
    }

    // Check for 'Content-Length'. If there's no data, we don't expect $_POST
    // to exist.
    $length = (int)$_SERVER['CONTENT_LENGTH'];
    if (!$length) {
      return;
    }

    // Time to fatal: we know this was a POST with data that should have been
    // populated into $_POST, but it wasn't.

    $config = ini_get('post_max_size');
    self::didFatal(
      "As received by the server, this request had a nonzero content length ".
      "but no POST data.\n\n".
      "Normally, this indicates that it exceeds the 'post_max_size' setting ".
      "in the PHP configuration on the server. Increase the 'post_max_size' ".
      "setting or reduce the size of the request.\n\n".
      "Request size according to 'Content-Length' was '{$length}', ".
      "'post_max_size' is set to '{$config}'.");
  }


/* -(  Rate Limiting  )------------------------------------------------------ */


  /**
   * Adjust the permissible rate limit score.
   *
   * By default, the limit is `1000`. You can use this method to set it to
   * a larger or smaller value. If you set it to `2000`, users may make twice
   * as many requests before rate limiting.
   *
   * @param int Maximum score before rate limiting.
   * @return void
   * @task ratelimit
   */
  public static function setMaximumRate($rate) {
    self::$maximumRate = $rate;
  }


  /**
   * Check if the user (identified by `$user_identity`) has issued too many
   * requests recently. If they have, end the request with a 429 error code.
   *
   * The key just needs to identify the user. Phabricator uses both user PHIDs
   * and user IPs as keys, tracking logged-in and logged-out users separately
   * and enforcing different limits.
   *
   * @param   string  Some key which identifies the user making the request.
   * @return  void    If the user has exceeded the rate limit, this method
   *                  does not return.
   * @task ratelimit
   */
  public static function rateLimitRequest($user_identity) {
    if (!self::canRateLimit()) {
      return;
    }

    $score = self::getRateLimitScore($user_identity);
    if ($score > (self::$maximumRate * self::getRateLimitBucketCount())) {
      // Give the user some bonus points for getting rate limited. This keeps
      // bad actors who keep slamming the 429 page locked out completely,
      // instead of letting them get a burst of requests through every minute
      // after a bucket expires.
      self::addRateLimitScore($user_identity, 50);
      self::didRateLimit($user_identity);
    }
  }


  /**
   * Add points to the rate limit score for some user.
   *
   * If users have earned more than 1000 points per minute across all the
   * buckets they'll be locked out of the application, so awarding 1 point per
   * request roughly corresponds to allowing 1000 requests per second, while
   * awarding 50 points roughly corresponds to allowing 20 requests per second.
   *
   * @param string  Some key which identifies the user making the request.
   * @param float   The cost for this request; more points pushes them toward
   *                the limit faster.
   * @return void
   * @task ratelimit
   */
  public static function addRateLimitScore($user_identity, $score) {
    if (!self::canRateLimit()) {
      return;
    }

    $current = self::getRateLimitBucket();

    // There's a bit of a race here, if a second process reads the bucket before
    // this one writes it, but it's fine if we occasionally fail to record a
    // user's score. If they're making requests fast enough to hit rate
    // limiting, we'll get them soon.

    $bucket_key = self::getRateLimitBucketKey($current);
    $bucket = apc_fetch($bucket_key);
    if (!is_array($bucket)) {
      $bucket = array();
    }

    if (empty($bucket[$user_identity])) {
      $bucket[$user_identity] = 0;
    }

    $bucket[$user_identity] += $score;
    apc_store($bucket_key, $bucket);
  }


  /**
   * Determine if rate limiting is available.
   *
   * Rate limiting depends on APC, and isn't available unless the APC user
   * cache is available.
   *
   * @return bool True if rate limiting is available.
   * @task ratelimit
   */
  private static function canRateLimit() {
    if (!self::$maximumRate) {
      return false;
    }

    if (!function_exists('apc_fetch')) {
      return false;
    }

    return true;
  }


  /**
   * Get the current bucket for storing rate limit scores.
   *
   * @return int The current bucket.
   * @task ratelimit
   */
  private static function getRateLimitBucket() {
    return (int)(time() / 60);
  }


  /**
   * Get the total number of rate limit buckets to retain.
   *
   * @return int Total number of rate limit buckets to retain.
   * @task ratelimit
   */
  private static function getRateLimitBucketCount() {
    return 5;
  }


  /**
   * Get the APC key for a given bucket.
   *
   * @param int Bucket to get the key for.
   * @return string APC key for the bucket.
   * @task ratelimit
   */
  private static function getRateLimitBucketKey($bucket) {
    return 'rate:bucket:'.$bucket;
  }


  /**
   * Get the APC key for the smallest stored bucket.
   *
   * @return string APC key for the smallest stored bucket.
   * @task ratelimit
   */
  private static function getRateLimitMinKey() {
    return 'rate:min';
  }


  /**
   * Get the current rate limit score for a given user.
   *
   * @param string Unique key identifying the user.
   * @return float The user's current score.
   * @task ratelimit
   */
  private static function getRateLimitScore($user_identity) {
    $min_key = self::getRateLimitMinKey();

    // Identify the oldest bucket stored in APC.
    $cur = self::getRateLimitBucket();
    $min = apc_fetch($min_key);

    // If we don't have any buckets stored yet, store the current bucket as
    // the oldest bucket.
    if (!$min) {
      apc_store($min_key, $cur);
      $min = $cur;
    }

    // Destroy any buckets that are older than the minimum bucket we're keeping
    // track of. Under load this normally shouldn't do anything, but will clean
    // up an old bucket once per minute.
    $count = self::getRateLimitBucketCount();
    for ($cursor = $min; $cursor < ($cur - $count); $cursor++) {
      apc_delete(self::getRateLimitBucketKey($cursor));
      apc_store($min_key, $cursor + 1);
    }

    // Now, sum up the user's scores in all of the active buckets.
    $score = 0;
    for (; $cursor <= $cur; $cursor++) {
      $bucket = apc_fetch(self::getRateLimitBucketKey($cursor));
      if (isset($bucket[$user_identity])) {
        $score += $bucket[$user_identity];
      }
    }

    return $score;
  }


  /**
   * Emit an HTTP 429 "Too Many Requests" response (indicating that the user
   * has exceeded application rate limits) and exit.
   *
   * @return exit This method **does not return**.
   * @task ratelimit
   */
  private static function didRateLimit() {
    $message =
      "TOO MANY REQUESTS\n".
      "You are issuing too many requests too quickly.\n".
      "To adjust limits, see \"Configuring a Preamble Script\" in the ".
      "documentation.";

    header(
      'Content-Type: text/plain; charset=utf-8',
      $replace = true,
      $http_error = 429);

    echo $message;

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
