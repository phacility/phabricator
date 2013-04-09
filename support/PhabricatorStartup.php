<?php

/**
 * Handle request startup, before loading the environment or libraries. This
 * class bootstraps the request state up to the point where we can enter
 * Phabricator code.
 *
 * NOTE: This class MUST NOT have any dependencies. It runs before libraries
 * load.
 *
 * @task info         Accessing Request Information
 * @task hook         Startup Hooks
 * @task apocalypse   In Case Of Apocalypse
 * @task validation   Validation
 */
final class PhabricatorStartup {

  private static $startTime;
  private static $globals = array();
  private static $capturingOutput;


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
  public static function setGlobal($key, $value) {
    self::validateGlobal($key);

    self::$globals[$key] = $value;
  }


  /**
   * @task info
   */
  public static function getGlobal($key, $default = null) {
    self::validateGlobal($key);

    if (!array_key_exists($key, self::$globals)) {
      return $default;
    }
    return self::$globals[$key];
  }


/* -(  Startup Hooks  )------------------------------------------------------ */


  /**
   * @task hook
   */
  public static function didStartup() {
    self::$startTime = microtime(true);
    self::$globals = array();

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

    self::verifyRewriteRules();

    self::detectPostMaxSizeTriggered();

    self::beginOutputCapture();
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
      self::didFatal("Already capturing output!");
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


/* -(  In Case of Apocalypse  )---------------------------------------------- */


  /**
   * @task apocalypse
   */
  public static function didFatal($message) {
    self::endOutputCapture();
    $access_log = self::getGlobal('log.access');

    if ($access_log) {
      // We may end up here before the access log is initialized, e.g. from
      // verifyPHP().

      try {
        $access_log->setData(
          array(
            'c' => 500,
          ));
        $access_log->write();
      } catch (Exception $ex) {
        $message .= "\n(Moreover, unable to write to access log.)";
      }
    }

    header(
      'Content-Type: text/plain; charset=utf-8',
      $replace = true,
      $http_error = 500);

    error_log($message);
    echo $message;

    exit(1);
  }


/* -(  Validation  )--------------------------------------------------------- */


  /**
   * @task valiation
   */
  private static function setupPHP() {
    error_reporting(E_ALL | E_STRICT);
    ini_set('memory_limit', -1);
  }


  /**
   * @task valiation
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
  }


  /**
   * @task valiation
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
   * @task valiation
   */
  private static function validateGlobal($key) {
    static $globals = array(
      'log.access' => true,
    );

    if (empty($globals[$key])) {
      throw new Exception("Access to unknown startup global '{$key}'!");
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
    PhabricatorStartup::didFatal(
      "As received by the server, this request had a nonzero content length ".
      "but no POST data.\n\n".
      "Normally, this indicates that it exceeds the 'post_max_size' setting ".
      "in the PHP configuration on the server. Increase the 'post_max_size' ".
      "setting or reduce the size of the request.\n\n".
      "Request size according to 'Content-Length' was '{$length}', ".
      "'post_max_size' is set to '{$config}'.");
  }

}
