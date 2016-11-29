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
final class PhabricatorEnv extends Phobject {

  private static $sourceStack;
  private static $repairSource;
  private static $overrideSource;
  private static $requestBaseURI;
  private static $cache;
  private static $localeCode;
  private static $readOnly;
  private static $readOnlyReason;

  const READONLY_CONFIG = 'config';
  const READONLY_UNREACHABLE = 'unreachable';
  const READONLY_SEVERED = 'severed';
  const READONLY_MASTERLESS = 'masterless';

  /**
   * @phutil-external-symbol class PhabricatorStartup
   */
  public static function initializeWebEnvironment() {
    self::initializeCommonEnvironment(false);
  }

  public static function initializeScriptEnvironment($config_optional) {
    self::initializeCommonEnvironment($config_optional);

    // NOTE: This is dangerous in general, but we know we're in a script context
    // and are not vulnerable to CSRF.
    AphrontWriteGuard::allowDangerousUnguardedWrites(true);

    // There are several places where we log information (about errors, events,
    // service calls, etc.) for analysis via DarkConsole or similar. These are
    // useful for web requests, but grow unboundedly in long-running scripts and
    // daemons. Discard data as it arrives in these cases.
    PhutilServiceProfiler::getInstance()->enableDiscardMode();
    DarkConsoleErrorLogPluginAPI::enableDiscardMode();
    DarkConsoleEventPluginAPI::enableDiscardMode();
  }


  private static function initializeCommonEnvironment($config_optional) {
    PhutilErrorHandler::initialize();

    self::resetUmask();
    self::buildConfigurationSourceStack($config_optional);

    // Force a valid timezone. If both PHP and Phabricator configuration are
    // invalid, use UTC.
    $tz = self::getEnvConfig('phabricator.timezone');
    if ($tz) {
      @date_default_timezone_set($tz);
    }
    $ok = @date_default_timezone_set(date_default_timezone_get());
    if (!$ok) {
      date_default_timezone_set('UTC');
    }

    // Prepend '/support/bin' and append any paths to $PATH if we need to.
    $env_path = getenv('PATH');
    $phabricator_path = dirname(phutil_get_library_root('phabricator'));
    $support_path = $phabricator_path.'/support/bin';
    $env_path = $support_path.PATH_SEPARATOR.$env_path;
    $append_dirs = self::getEnvConfig('environment.append-paths');
    if (!empty($append_dirs)) {
      $append_path = implode(PATH_SEPARATOR, $append_dirs);
      $env_path = $env_path.PATH_SEPARATOR.$append_path;
    }
    putenv('PATH='.$env_path);

    // Write this back into $_ENV, too, so ExecFuture picks it up when creating
    // subprocess environments.
    $_ENV['PATH'] = $env_path;


    // If an instance identifier is defined, write it into the environment so
    // it's available to subprocesses.
    $instance = self::getEnvConfig('cluster.instance');
    if (strlen($instance)) {
      putenv('PHABRICATOR_INSTANCE='.$instance);
      $_ENV['PHABRICATOR_INSTANCE'] = $instance;
    }

    PhabricatorEventEngine::initialize();

    // TODO: Add a "locale.default" config option once we have some reasonable
    // defaults which aren't silly nonsense.
    self::setLocaleCode('en_US');
  }

  public static function beginScopedLocale($locale_code) {
    return new PhabricatorLocaleScopeGuard($locale_code);
  }

  public static function getLocaleCode() {
    return self::$localeCode;
  }

  public static function setLocaleCode($locale_code) {
    if (!$locale_code) {
      return;
    }

    if ($locale_code == self::$localeCode) {
      return;
    }

    try {
      $locale = PhutilLocale::loadLocale($locale_code);
      $translations = PhutilTranslation::getTranslationMapForLocale(
        $locale_code);

      $override = self::getEnvConfig('translation.override');
      if (!is_array($override)) {
        $override = array();
      }

      PhutilTranslator::getInstance()
        ->setLocale($locale)
        ->setTranslations($override + $translations);

      self::$localeCode = $locale_code;
    } catch (Exception $ex) {
      // Just ignore this; the user likely has an out-of-date locale code.
    }
  }

  private static function buildConfigurationSourceStack($config_optional) {
    self::dropConfigCache();

    $stack = new PhabricatorConfigStackSource();
    self::$sourceStack = $stack;

    $default_source = id(new PhabricatorConfigDefaultSource())
      ->setName(pht('Global Default'));
    $stack->pushSource($default_source);

    $env = self::getSelectedEnvironmentName();
    if ($env) {
      $stack->pushSource(
        id(new PhabricatorConfigFileSource($env))
          ->setName(pht("File '%s'", $env)));
    }

    $stack->pushSource(
      id(new PhabricatorConfigLocalSource())
        ->setName(pht('Local Config')));

    // If the install overrides the database adapter, we might need to load
    // the database adapter class before we can push on the database config.
    // This config is locked and can't be edited from the web UI anyway.
    foreach (self::getEnvConfig('load-libraries') as $library) {
      phutil_load_library($library);
    }

    // Drop any class map caches, since they will have generated without
    // any classes from libraries. Without this, preflight setup checks can
    // cause generation of a setup check cache that omits checks defined in
    // libraries, for example.
    PhutilClassMapQuery::deleteCaches();

    // If custom libraries specify config options, they won't get default
    // values as the Default source has already been loaded, so we get it to
    // pull in all options from non-phabricator libraries now they are loaded.
    $default_source->loadExternalOptions();

    // If this install has site config sources, load them now.
    $site_sources = id(new PhutilClassMapQuery())
      ->setAncestorClass('PhabricatorConfigSiteSource')
      ->setSortMethod('getPriority')
      ->execute();

    foreach ($site_sources as $site_source) {
      $stack->pushSource($site_source);
    }

    $masters = PhabricatorDatabaseRef::getMasterDatabaseRefs();
    if (!$masters) {
      self::setReadOnly(true, self::READONLY_MASTERLESS);
    } else {
      // If any master is severed, we drop to readonly mode. In theory we
      // could try to continue if we're only missing some applications, but
      // this is very complex and we're unlikely to get it right.

      foreach ($masters as $master) {
        // Give severed masters one last chance to get healthy.
        if ($master->isSevered()) {
          $master->checkHealth();
        }

        if ($master->isSevered()) {
          self::setReadOnly(true, self::READONLY_SEVERED);
          break;
        }
      }
    }

    try {
      $stack->pushSource(
        id(new PhabricatorConfigDatabaseSource('default'))
          ->setName(pht('Database')));
    } catch (AphrontSchemaQueryException $exception) {
      // If the database is not available, just skip this configuration
      // source. This happens during `bin/storage upgrade`, `bin/conf` before
      // schema setup, etc.
    } catch (PhabricatorClusterStrandedException $ex) {
      // This means we can't connect to any database host. That's fine as
      // long as we're running a setup script like `bin/storage`.
      if (!$config_optional) {
        throw $ex;
      }
    }
  }

  public static function repairConfig($key, $value) {
    if (!self::$repairSource) {
      self::$repairSource = id(new PhabricatorConfigDictionarySource(array()))
        ->setName(pht('Repaired Config'));
      self::$sourceStack->pushSource(self::$repairSource);
    }
    self::$repairSource->setKeys(array($key => $value));
    self::dropConfigCache();
  }

  public static function overrideConfig($key, $value) {
    if (!self::$overrideSource) {
      self::$overrideSource = id(new PhabricatorConfigDictionarySource(array()))
        ->setName(pht('Overridden Config'));
      self::$sourceStack->pushSource(self::$overrideSource);
    }
    self::$overrideSource->setKeys(array($key => $value));
    self::dropConfigCache();
  }

  public static function getUnrepairedEnvConfig($key, $default = null) {
    foreach (self::$sourceStack->getStack() as $source) {
      if ($source === self::$repairSource) {
        continue;
      }
      $result = $source->getKeys(array($key));
      if ($result) {
        return $result[$key];
      }
    }
    return $default;
  }

  public static function getSelectedEnvironmentName() {
    $env_var = 'PHABRICATOR_ENV';

    $env = idx($_SERVER, $env_var);

    if (!$env) {
      $env = getenv($env_var);
    }

    if (!$env) {
      $env = idx($_ENV, $env_var);
    }

    if (!$env) {
      $root = dirname(phutil_get_library_root('phabricator'));
      $path = $root.'/conf/local/ENVIRONMENT';
      if (Filesystem::pathExists($path)) {
        $env = trim(Filesystem::readFile($path));
      }
    }

    return $env;
  }


/* -(  Reading Configuration  )---------------------------------------------- */


  /**
   * Get the current configuration setting for a given key.
   *
   * If the key is not found, then throw an Exception.
   *
   * @task read
   */
  public static function getEnvConfig($key) {
    if (!self::$sourceStack) {
      throw new Exception(
        pht(
          'Trying to read configuration "%s" before configuration has been '.
          'initialized.',
          $key));
    }

    if (isset(self::$cache[$key])) {
      return self::$cache[$key];
    }

    if (array_key_exists($key, self::$cache)) {
      return self::$cache[$key];
    }

    $result = self::$sourceStack->getKeys(array($key));
    if (array_key_exists($key, $result)) {
      self::$cache[$key] = $result[$key];
      return $result[$key];
    } else {
      throw new Exception(
        pht(
          "No config value specified for key '%s'.",
          $key));
    }
  }

  /**
   * Get the current configuration setting for a given key. If the key
   * does not exist, return a default value instead of throwing. This is
   * primarily useful for migrations involving keys which are slated for
   * removal.
   *
   * @task read
   */
  public static function getEnvConfigIfExists($key, $default = null) {
    try {
      return self::getEnvConfig($key);
    } catch (Exception $ex) {
      return $default;
    }
  }


  /**
   * Get the fully-qualified URI for a path.
   *
   * @task read
   */
  public static function getURI($path) {
    return rtrim(self::getAnyBaseURI(), '/').$path;
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
      $production_domain = self::getAnyBaseURI();
    }
    return rtrim($production_domain, '/').$path;
  }

  public static function getAllowedURIs($path) {
    $uri = new PhutilURI($path);
    if ($uri->getDomain()) {
      return $path;
    }

    $allowed_uris = self::getEnvConfig('phabricator.allowed-uris');
    $return = array();
    foreach ($allowed_uris as $allowed_uri) {
      $return[] = rtrim($allowed_uri, '/').$path;
    }

    return $return;
  }


  /**
   * Get the fully-qualified production URI for a static resource path.
   *
   * @task read
   */
  public static function getCDNURI($path) {
    $alt = self::getEnvConfig('security.alternate-file-domain');
    if (!$alt) {
      $alt = self::getAnyBaseURI();
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
  public static function getDoclink($resource, $type = 'article') {
    $uri = new PhutilURI('https://secure.phabricator.com/diviner/find/');
    $uri->setQueryParam('name', $resource);
    $uri->setQueryParam('type', $type);
    $uri->setQueryParam('jump', true);
    return (string)$uri;
  }


  /**
   * Build a concrete object from a configuration key.
   *
   * @task read
   */
  public static function newObjectFromConfig($key, $args = array()) {
    $class = self::getEnvConfig($key);
    return newv($class, $args);
  }

  public static function getAnyBaseURI() {
    $base_uri = self::getEnvConfig('phabricator.base-uri');

    if (!$base_uri) {
      $base_uri = self::getRequestBaseURI();
    }

    if (!$base_uri) {
      throw new Exception(
        pht(
          "Define '%s' in your configuration to continue.",
          'phabricator.base-uri'));
    }

    return $base_uri;
  }

  public static function getRequestBaseURI() {
    return self::$requestBaseURI;
  }

  public static function setRequestBaseURI($uri) {
    self::$requestBaseURI = $uri;
  }

  public static function isReadOnly() {
    if (self::$readOnly !== null) {
      return self::$readOnly;
    }
    return self::getEnvConfig('cluster.read-only');
  }

  public static function setReadOnly($read_only, $reason) {
    self::$readOnly = $read_only;
    self::$readOnlyReason = $reason;
  }

  public static function getReadOnlyMessage() {
    $reason = self::getReadOnlyReason();
    switch ($reason) {
      case self::READONLY_MASTERLESS:
        return pht(
          'Phabricator is in read-only mode (no writable database '.
          'is configured).');
      case self::READONLY_UNREACHABLE:
        return pht(
          'Phabricator is in read-only mode (unreachable master).');
      case self::READONLY_SEVERED:
        return pht(
          'Phabricator is in read-only mode (major interruption).');
    }

    return pht('Phabricator is in read-only mode.');
  }

  public static function getReadOnlyURI() {
    return urisprintf(
      '/readonly/%s/',
      self::getReadOnlyReason());
  }

  public static function getReadOnlyReason() {
    if (!self::isReadOnly()) {
      return null;
    }

    if (self::$readOnlyReason !== null) {
      return self::$readOnlyReason;
    }

    return self::READONLY_CONFIG;
  }


/* -(  Unit Test Support  )-------------------------------------------------- */


  /**
   * @task test
   */
  public static function beginScopedEnv() {
    return new PhabricatorScopedEnv(self::pushTestEnvironment());
  }


  /**
   * @task test
   */
  private static function pushTestEnvironment() {
    self::dropConfigCache();
    $source = new PhabricatorConfigDictionarySource(array());
    self::$sourceStack->pushSource($source);
    return spl_object_hash($source);
  }


  /**
   * @task test
   */
  public static function popTestEnvironment($key) {
    self::dropConfigCache();
    $source = self::$sourceStack->popSource();
    $stack_key = spl_object_hash($source);
    if ($stack_key !== $key) {
      self::$sourceStack->pushSource($source);
      throw new Exception(
        pht(
          'Scoped environments were destroyed in a different order than they '.
          'were initialized.'));
    }
  }


/* -(  URI Validation  )----------------------------------------------------- */


  /**
   * Detect if a URI satisfies either @{method:isValidLocalURIForLink} or
   * @{method:isValidRemoteURIForLink}, i.e. is a page on this server or the
   * URI of some other resource which has a valid protocol. This rejects
   * garbage URIs and URIs with protocols which do not appear in the
   * `uri.allowed-protocols` configuration, notably 'javascript:' URIs.
   *
   * NOTE: This method is generally intended to reject URIs which it may be
   * unsafe to put in an "href" link attribute.
   *
   * @param string URI to test.
   * @return bool True if the URI identifies a web resource.
   * @task uri
   */
  public static function isValidURIForLink($uri) {
    return self::isValidLocalURIForLink($uri) ||
           self::isValidRemoteURIForLink($uri);
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
  public static function isValidLocalURIForLink($uri) {
    $uri = (string)$uri;

    if (!strlen($uri)) {
      return false;
    }

    if (preg_match('/\s/', $uri)) {
      // PHP hasn't been vulnerable to header injection attacks for a bunch of
      // years, but we can safely reject these anyway since they're never valid.
      return false;
    }

    // Chrome (at a minimum) interprets backslashes in Location headers and the
    // URL bar as forward slashes. This is probably intended to reduce user
    // error caused by confusion over which key is "forward slash" vs "back
    // slash".
    //
    // However, it means a URI like "/\evil.com" is interpreted like
    // "//evil.com", which is a protocol relative remote URI.
    //
    // Since we currently never generate URIs with backslashes in them, reject
    // these unconditionally rather than trying to figure out how browsers will
    // interpret them.
    if (preg_match('/\\\\/', $uri)) {
      return false;
    }

    // Valid URIs must begin with '/', followed by the end of the string or some
    // other non-'/' character. This rejects protocol-relative URIs like
    // "//evil.com/evil_stuff/".
    return (bool)preg_match('@^/([^/]|$)@', $uri);
  }


  /**
   * Detect if a URI identifies some valid linkable remote resource.
   *
   * @param string URI to test.
   * @return bool True if a URI idenfies a remote resource with an allowed
   *              protocol.
   * @task uri
   */
  public static function isValidRemoteURIForLink($uri) {
    try {
      self::requireValidRemoteURIForLink($uri);
      return true;
    } catch (Exception $ex) {
      return false;
    }
  }


  /**
   * Detect if a URI identifies a valid linkable remote resource, throwing a
   * detailed message if it does not.
   *
   * A valid linkable remote resource can be safely linked or redirected to.
   * This is primarily a protocol whitelist check.
   *
   * @param string URI to test.
   * @return void
   * @task uri
   */
  public static function requireValidRemoteURIForLink($raw_uri) {
    $uri = new PhutilURI($raw_uri);

    $proto = $uri->getProtocol();
    if (!strlen($proto)) {
      throw new Exception(
        pht(
          'URI "%s" is not a valid linkable resource. A valid linkable '.
          'resource URI must specify a protocol.',
          $raw_uri));
    }

    $protocols = self::getEnvConfig('uri.allowed-protocols');
    if (!isset($protocols[$proto])) {
      throw new Exception(
        pht(
          'URI "%s" is not a valid linkable resource. A valid linkable '.
          'resource URI must use one of these protocols: %s.',
          $raw_uri,
          implode(', ', array_keys($protocols))));
    }

    $domain = $uri->getDomain();
    if (!strlen($domain)) {
      throw new Exception(
        pht(
          'URI "%s" is not a valid linkable resource. A valid linkable '.
          'resource URI must specify a domain.',
          $raw_uri));
    }
  }


  /**
   * Detect if a URI identifies a valid fetchable remote resource.
   *
   * @param string URI to test.
   * @param list<string> Allowed protocols.
   * @return bool True if the URI is a valid fetchable remote resource.
   * @task uri
   */
  public static function isValidRemoteURIForFetch($uri, array $protocols) {
    try {
      self::requireValidRemoteURIForFetch($uri, $protocols);
      return true;
    } catch (Exception $ex) {
      return false;
    }
  }


  /**
   * Detect if a URI identifies a valid fetchable remote resource, throwing
   * a detailed message if it does not.
   *
   * A valid fetchable remote resource can be safely fetched using a request
   * originating on this server. This is a primarily an address check against
   * the outbound address blacklist.
   *
   * @param string URI to test.
   * @param list<string> Allowed protocols.
   * @return pair<string, string> Pre-resolved URI and domain.
   * @task uri
   */
  public static function requireValidRemoteURIForFetch(
    $uri,
    array $protocols) {

    $uri = new PhutilURI($uri);

    $proto = $uri->getProtocol();
    if (!strlen($proto)) {
      throw new Exception(
        pht(
          'URI "%s" is not a valid fetchable resource. A valid fetchable '.
          'resource URI must specify a protocol.',
          $uri));
    }

    $protocols = array_fuse($protocols);
    if (!isset($protocols[$proto])) {
      throw new Exception(
        pht(
          'URI "%s" is not a valid fetchable resource. A valid fetchable '.
          'resource URI must use one of these protocols: %s.',
          $uri,
          implode(', ', array_keys($protocols))));
    }

    $domain = $uri->getDomain();
    if (!strlen($domain)) {
      throw new Exception(
        pht(
          'URI "%s" is not a valid fetchable resource. A valid fetchable '.
          'resource URI must specify a domain.',
          $uri));
    }

    $addresses = gethostbynamel($domain);
    if (!$addresses) {
      throw new Exception(
        pht(
          'URI "%s" is not a valid fetchable resource. The domain "%s" could '.
          'not be resolved.',
          $uri,
          $domain));
    }

    foreach ($addresses as $address) {
      if (self::isBlacklistedOutboundAddress($address)) {
        throw new Exception(
          pht(
            'URI "%s" is not a valid fetchable resource. The domain "%s" '.
            'resolves to the address "%s", which is blacklisted for '.
            'outbound requests.',
            $uri,
            $domain,
            $address));
      }
    }

    $resolved_uri = clone $uri;
    $resolved_uri->setDomain(head($addresses));

    return array($resolved_uri, $domain);
  }


  /**
   * Determine if an IP address is in the outbound address blacklist.
   *
   * @param string IP address.
   * @return bool True if the address is blacklisted.
   */
  public static function isBlacklistedOutboundAddress($address) {
    $blacklist = self::getEnvConfig('security.outbound-blacklist');

    return PhutilCIDRList::newList($blacklist)->containsAddress($address);
  }

  public static function isClusterRemoteAddress() {
    $cluster_addresses = self::getEnvConfig('cluster.addresses');
    if (!$cluster_addresses) {
      return false;
    }

    $address = idx($_SERVER, 'REMOTE_ADDR');
    if (!$address) {
      throw new Exception(
        pht(
          'Unable to test remote address against cluster whitelist: '.
          'REMOTE_ADDR is not defined.'));
    }

    return self::isClusterAddress($address);
  }

  public static function isClusterAddress($address) {
    $cluster_addresses = self::getEnvConfig('cluster.addresses');
    if (!$cluster_addresses) {
      throw new Exception(
        pht(
          'Phabricator is not configured to serve cluster requests. '.
          'Set `cluster.addresses` in the configuration to whitelist '.
          'cluster hosts before sending requests that use a cluster '.
          'authentication mechanism.'));
    }

    return PhutilCIDRList::newList($cluster_addresses)
      ->containsAddress($address);
  }

/* -(  Internals  )---------------------------------------------------------- */


  /**
   * @task internal
   */
  public static function envConfigExists($key) {
    return array_key_exists($key, self::$sourceStack->getKeys(array($key)));
  }


  /**
   * @task internal
   */
  public static function getAllConfigKeys() {
    return self::$sourceStack->getAllKeys();
  }

  public static function getConfigSourceStack() {
    return self::$sourceStack;
  }

  /**
   * @task internal
   */
  public static function overrideTestEnvConfig($stack_key, $key, $value) {
    $tmp = array();

    // If we don't have the right key, we'll throw when popping the last
    // source off the stack.
    do {
      $source = self::$sourceStack->popSource();
      array_unshift($tmp, $source);
      if (spl_object_hash($source) == $stack_key) {
        $source->setKeys(array($key => $value));
        break;
      }
    } while (true);

    foreach ($tmp as $source) {
      self::$sourceStack->pushSource($source);
    }

    self::dropConfigCache();
  }

  private static function dropConfigCache() {
    self::$cache = array();
  }

  private static function resetUmask() {
    // Reset the umask to the common standard umask. The umask controls default
    // permissions when files are created and propagates to subprocesses.

    // "022" is the most common umask, but sometimes it is set to something
    // unusual by the calling environment.

    // Since various things rely on this umask to work properly and we are
    // not aware of any legitimate reasons to adjust it, unconditionally
    // normalize it until such reasons arise. See T7475 for discussion.
    umask(022);
  }


  /**
   * Get the path to an empty directory which is readable by all of the system
   * user accounts that Phabricator acts as.
   *
   * In some cases, a binary needs some valid HOME or CWD to continue, but not
   * all user accounts have valid home directories and even if they do they
   * may not be readable after a `sudo` operation.
   *
   * @return string Path to an empty directory suitable for use as a CWD.
   */
  public static function getEmptyCWD() {
    $root = dirname(phutil_get_library_root('phabricator'));
    return $root.'/support/empty/';
  }


}
