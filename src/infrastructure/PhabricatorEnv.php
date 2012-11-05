<?php

/**
 * Manages the execution environment configuration, exposing APIs to read
 * configuration settings and other similar values that are derived directly
 * from configuration settings.
 *
 *
 * = Reading Configuration =
 *
 * The primary role of this class is to provide an API for reading
 * Phabricator configuration, @{method:getEnvConfig}:
 *
 *   $value = PhabricatorEnv::getEnvConfig('some.key', $default);
 *
 * The class also handles some URI construction based on configuration, via
 * the methods @{method:getURI}, @{method:getProductionURI},
 * @{method:getCDNURI}, and @{method:getDoclink}.
 *
 * For configuration which allows you to choose a class to be responsible for
 * some functionality (e.g., which mail adapter to use to deliver email),
 * @{method:newObjectFromConfig} provides a simple interface that validates
 * the configured value.
 *
 *
 * = Unit Test Support =
 *
 * In unit tests, you can use @{method:beginScopedEnv} to create a temporary,
 * mutable environment. The method returns a scope guard object which restores
 * the environment when it is destroyed. For example:
 *
 *   public function testExample() {
 *     $env = PhabricatorEnv::beginScopedEnv();
 *     $env->overrideEnv('some.key', 'new-value-for-this-test');
 *
 *     // Some test which depends on the value of 'some.key'.
 *
 *   }
 *
 * Your changes will persist until the `$env` object leaves scope or is
 * destroyed.
 *
 * You should //not// use this in normal code.
 *
 *
 * @task read     Reading Configuration
 * @task uri      URI Validation
 * @task test     Unit Test Support
 * @task internal Internals
 */
final class PhabricatorEnv {

  private static $env;
  private static $stack = array();


/* -(  Reading Configuration  )---------------------------------------------- */


  /**
   * Get the current configuration setting for a given key.
   *
   * @task read
   */
  public static function getEnvConfig($key, $default = null) {

    // If we have environment overrides via beginScopedEnv(), check them for
    // the key first.
    if (self::$stack) {
      foreach (array_reverse(self::$stack) as $override) {
        if (array_key_exists($key, $override)) {
          return $override[$key];
        }
      }
    }

    return idx(self::$env, $key, $default);
  }


  /**
   * Get the fully-qualified URI for a path.
   *
   * @task read
   */
  public static function getURI($path) {
    return rtrim(self::getEnvConfig('phabricator.base-uri'), '/').$path;
  }


  /**
   * Get the fully-qualified production URI for a path.
   *
   * @task read
   */
  public static function getProductionURI($path) {
    // If we're passed a URI which already has a domain, simply return it
    // unmodified. In particular, files may have URIs which point to a CDN
    // domain.
    $uri = new PhutilURI($path);
    if ($uri->getDomain()) {
      return $path;
    }

    $production_domain = self::getEnvConfig('phabricator.production-uri');
    if (!$production_domain) {
      $production_domain = self::getEnvConfig('phabricator.base-uri');
    }
    return rtrim($production_domain, '/').$path;
  }


  /**
   * Get the fully-qualified production URI for a static resource path.
   *
   * @task read
   */
  public static function getCDNURI($path) {
    $alt = self::getEnvConfig('security.alternate-file-domain');
    if (!$alt) {
      $alt = self::getEnvConfig('phabricator.base-uri');
    }
    $uri = new PhutilURI($alt);
    $uri->setPath($path);
    return (string)$uri;
  }


  /**
   * Get the fully-qualified production URI for a documentation resource.
   *
   * @task read
   */
  public static function getDoclink($resource) {
    return 'http://www.phabricator.com/docs/phabricator/'.$resource;
  }


  /**
   * Build a concrete object from a configuration key.
   *
   * @task read
   */
  public static function newObjectFromConfig($key, $args = array()) {
    $class = self::getEnvConfig($key);
    $object = newv($class, $args);
    $instanceof = idx(self::getRequiredClasses(), $key);
    if (!($object instanceof $instanceof)) {
      throw new Exception("Config setting '$key' must be an instance of ".
        "'$instanceof', is '".get_class($object)."'.");
    }
    return $object;
  }


/* -(  Unit Test Support  )-------------------------------------------------- */


  /**
   * @task test
   */
  public static function beginScopedEnv() {
    return new PhabricatorScopedEnv(self::pushEnvironment());
  }


  /**
   * @task test
   */
  private static function pushEnvironment() {
    self::$stack[] = array();
    return last_key(self::$stack);
  }


  /**
   * @task test
   */
  public static function popEnvironment($key) {
    $stack_key = last_key(self::$stack);

    array_pop(self::$stack);

    if ($stack_key !== $key) {
      throw new Exception(
        "Scoped environments were destroyed in a diffent order than they ".
        "were initialized.");
    }
  }


/* -(  URI Validation  )----------------------------------------------------- */


  /**
   * Detect if a URI satisfies either @{method:isValidLocalWebResource} or
   * @{method:isValidRemoteWebResource}, i.e. is a page on this server or the
   * URI of some other resource which has a valid protocol. This rejects
   * garbage URIs and URIs with protocols which do not appear in the
   * ##uri.allowed-protocols## configuration, notably 'javascript:' URIs.
   *
   * NOTE: This method is generally intended to reject URIs which it may be
   * unsafe to put in an "href" link attribute.
   *
   * @param string URI to test.
   * @return bool True if the URI identifies a web resource.
   * @task uri
   */
  public static function isValidWebResource($uri) {
    return self::isValidLocalWebResource($uri) ||
           self::isValidRemoteWebResource($uri);
  }


  /**
   * Detect if a URI identifies some page on this server.
   *
   * NOTE: This method is generally intended to reject URIs which it may be
   * unsafe to issue a "Location:" redirect to.
   *
   * @param string URI to test.
   * @return bool True if the URI identifies a local page.
   * @task uri
   */
  public static function isValidLocalWebResource($uri) {
    $uri = (string)$uri;

    if (!strlen($uri)) {
      return false;
    }

    if (preg_match('/\s/', $uri)) {
      // PHP hasn't been vulnerable to header injection attacks for a bunch of
      // years, but we can safely reject these anyway since they're never valid.
      return false;
    }

    // Valid URIs must begin with '/', followed by the end of the string or some
    // other non-'/' character. This rejects protocol-relative URIs like
    // "//evil.com/evil_stuff/".
    return (bool)preg_match('@^/([^/]|$)@', $uri);
  }


  /**
   * Detect if a URI identifies some valid remote resource.
   *
   * @param string URI to test.
   * @return bool True if a URI idenfies a remote resource with an allowed
   *              protocol.
   * @task uri
   */
  public static function isValidRemoteWebResource($uri) {
    $uri = (string)$uri;

    $proto = id(new PhutilURI($uri))->getProtocol();
    if (!$proto) {
      return false;
    }

    $allowed = self::getEnvConfig('uri.allowed-protocols');
    if (empty($allowed[$proto])) {
      return false;
    }

    return true;
  }


/* -(  Internals  )---------------------------------------------------------- */


  /**
   * @task internal
   */
  public static function setEnvConfig(array $config) {
    self::$env = $config;
  }


  /**
   * @task internal
   */
  public static function getRequiredClasses() {
    return array(
      'translation.provider' => 'PhabricatorTranslation',
      'metamta.mail-adapter' => 'PhabricatorMailImplementationAdapter',
      'metamta.maniphest.reply-handler' => 'PhabricatorMailReplyHandler',
      'metamta.differential.reply-handler' => 'PhabricatorMailReplyHandler',
      'metamta.diffusion.reply-handler' => 'PhabricatorMailReplyHandler',
      'metamta.package.reply-handler' => 'PhabricatorMailReplyHandler',
      'storage.engine-selector' => 'PhabricatorFileStorageEngineSelector',
      'search.engine-selector' => 'PhabricatorSearchEngineSelector',
      'differential.field-selector' => 'DifferentialFieldSelector',
      'maniphest.custom-task-extensions-class' => 'ManiphestTaskExtensions',
      'aphront.default-application-configuration-class' =>
        'AphrontApplicationConfiguration',
      'controller.oauth-registration' =>
        'PhabricatorOAuthRegistrationController',
      'mysql.implementation' => 'AphrontMySQLDatabaseConnectionBase',
      'differential.attach-task-class' => 'DifferentialTasksAttacher',
      'mysql.configuration-provider' => 'DatabaseConfigurationProvider',
      'syntax-highlighter.engine' => 'PhutilSyntaxHighlighterEngine',
    );
  }


  /**
   * @task internal
   */
  public static function envConfigExists($key) {
    return array_key_exists($key, self::$env);
  }


  /**
   * @task internal
   */
  public static function getAllConfigKeys() {
    return self::$env;
  }


  /**
   * @task internal
   */
  public static function overrideEnvConfig($stack_key, $key, $value) {
    self::$stack[$stack_key][$key] = $value;
  }

}
