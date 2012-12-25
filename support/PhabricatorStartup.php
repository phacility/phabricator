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

    self::setupPHP();
    self::verifyPHP();

    self::verifyRewriteRules();

    static $registered;
    if (!$registered) {
      // NOTE: This protects us against multiple calls to didStartup() in the
      // same request, but also against repeated requests to the same
      // interpreter state, which we may implement in the future.
      register_shutdown_function(array(__CLASS__, 'didShutdown'));
      $registered = true;
    }
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


/* -(  In Case of Apocalypse  )---------------------------------------------- */


  /**
   * @task apocalypse
   */
  public static function didFatal($message) {
    $access_log = self::getGlobal('log.access');

    if ($access_log) {
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
    if (isset($_REQUEST['__path__'])) {
      return;
    }

    if (php_sapi_name() == 'cli-server') {
      // Compatibility with PHP 5.4+ built-in web server.
      $url = parse_url($_SERVER['REQUEST_URI']);
      $_REQUEST['__path__'] = $url['path'];
    } else {
      self::didFatal(
        "Request parameter '__path__' is not set. Your rewrite rules ".
        "are not configured correctly.");
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

}
