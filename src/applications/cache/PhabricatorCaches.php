<?php

/**
 *
 * @task request    Request Cache
 * @task immutable  Immutable Cache
 * @task setup      Setup Cache
 * @task compress   Compression
 */
final class PhabricatorCaches extends Phobject {

  private static $requestCache;

  public static function getNamespace() {
    return PhabricatorEnv::getEnvConfig('phabricator.cache-namespace');
  }

  private static function newStackFromCaches(array $caches) {
    $caches = self::addNamespaceToCaches($caches);
    $caches = self::addProfilerToCaches($caches);
    return id(new PhutilKeyValueCacheStack())
      ->setCaches($caches);
  }

/* -(  Request Cache  )------------------------------------------------------ */


  /**
   * Get a request cache stack.
   *
   * This cache stack is destroyed after each logical request. In particular,
   * it is destroyed periodically by the daemons, while `static` caches are
   * not.
   *
   * @return PhutilKeyValueCacheStack Request cache stack.
   */
  public static function getRequestCache() {
    if (!self::$requestCache) {
      self::$requestCache = new PhutilInRequestKeyValueCache();
    }
    return self::$requestCache;
  }


  /**
   * Destroy the request cache.
   *
   * This is called at the beginning of each logical request.
   *
   * @return void
   */
  public static function destroyRequestCache() {
    self::$requestCache = null;
  }


/* -(  Immutable Cache  )---------------------------------------------------- */


  /**
   * Gets an immutable cache stack.
   *
   * This stack trades mutability away for improved performance. Normally, it is
   * APC + DB.
   *
   * In the general case with multiple web frontends, this stack can not be
   * cleared, so it is only appropriate for use if the value of a given key is
   * permanent and immutable.
   *
   * @return PhutilKeyValueCacheStack Best immutable stack available.
   * @task immutable
   */
  public static function getImmutableCache() {
    static $cache;
    if (!$cache) {
      $caches = self::buildImmutableCaches();
      $cache = self::newStackFromCaches($caches);
    }
    return $cache;
  }


  /**
   * Build the immutable cache stack.
   *
   * @return list<PhutilKeyValueCache> List of caches.
   * @task immutable
   */
  private static function buildImmutableCaches() {
    $caches = array();

    $apc = new PhutilAPCKeyValueCache();
    if ($apc->isAvailable()) {
      $caches[] = $apc;
    }

    $caches[] = new PhabricatorKeyValueDatabaseCache();

    return $caches;
  }


/* -(  Repository Graph Cache  )--------------------------------------------- */


  public static function getRepositoryGraphL1Cache() {
    static $cache;
    if (!$cache) {
      $caches = self::buildRepositoryGraphL1Caches();
      $cache = self::newStackFromCaches($caches);
    }
    return $cache;
  }

  private static function buildRepositoryGraphL1Caches() {
    $caches = array();

    $request = new PhutilInRequestKeyValueCache();
    $request->setLimit(32);
    $caches[] = $request;

    $apc = new PhutilAPCKeyValueCache();
    if ($apc->isAvailable()) {
      $caches[] = $apc;
    }

    return $caches;
  }

  public static function getRepositoryGraphL2Cache() {
    static $cache;
    if (!$cache) {
      $caches = self::buildRepositoryGraphL2Caches();
      $cache = self::newStackFromCaches($caches);
    }
    return $cache;
  }

  private static function buildRepositoryGraphL2Caches() {
    $caches = array();
    $caches[] = new PhabricatorKeyValueDatabaseCache();
    return $caches;
  }


/* -(  Setup Cache  )-------------------------------------------------------- */


  /**
   * Highly specialized cache for performing setup checks. We use this cache
   * to determine if we need to run expensive setup checks when the page
   * loads. Without it, we would need to run these checks every time.
   *
   * Normally, this cache is just APC. In the absence of APC, this cache
   * degrades into a slow, quirky on-disk cache.
   *
   * NOTE: Do not use this cache for anything else! It is not a general-purpose
   * cache!
   *
   * @return PhutilKeyValueCacheStack Most qualified available cache stack.
   * @task setup
   */
  public static function getSetupCache() {
    static $cache;
    if (!$cache) {
      $caches = self::buildSetupCaches();
      $cache = self::newStackFromCaches($caches);
    }
    return $cache;
  }


  /**
   * @task setup
   */
  private static function buildSetupCaches() {
    // In most cases, we should have APC. This is an ideal cache for our
    // purposes -- it's fast and empties on server restart.
    $apc = new PhutilAPCKeyValueCache();
    if ($apc->isAvailable()) {
      return array($apc);
    }

    // If we don't have APC, build a poor approximation on disk. This is still
    // much better than nothing; some setup steps are quite slow.
    $disk_path = self::getSetupCacheDiskCachePath();
    if ($disk_path) {
      $disk = new PhutilOnDiskKeyValueCache();
      $disk->setCacheFile($disk_path);
      $disk->setWait(0.1);
      if ($disk->isAvailable()) {
        return array($disk);
      }
    }

    return array();
  }


  /**
   * @task setup
   */
  private static function getSetupCacheDiskCachePath() {
    // The difficulty here is in choosing a path which will change on server
    // restart (we MUST have this property), but as rarely as possible
    // otherwise (we desire this property to give the cache the best hit rate
    // we can).

    // In some setups, the parent PID is more stable and longer-lived that the
    // PID (e.g., under apache, our PID will be a worker while the ppid will
    // be the main httpd process). If we're confident we're running under such
    // a setup, we can try to use the PPID as the basis for our cache instead
    // of our own PID.
    $use_ppid = false;

    switch (php_sapi_name()) {
      case 'cli-server':
        // This is the PHP5.4+ built-in webserver. We should use the pid
        // (the server), not the ppid (probably a shell or something).
        $use_ppid = false;
        break;
      case 'fpm-fcgi':
        // We should be safe to use PPID here.
        $use_ppid = true;
        break;
      case 'apache2handler':
        // We're definitely safe to use the PPID.
        $use_ppid = true;
        break;
    }

    $pid_basis = getmypid();
    if ($use_ppid) {
      if (function_exists('posix_getppid')) {
        $parent_pid = posix_getppid();
        // On most systems, normal processes can never have PIDs lower than 100,
        // so something likely went wrong if we we get one of these.
        if ($parent_pid > 100) {
          $pid_basis = $parent_pid;
        }
      }
    }

    // If possible, we also want to know when the process launched, so we can
    // drop the cache if a process restarts but gets the same PID an earlier
    // process had. "/proc" is not available everywhere (e.g., not on OSX), but
    // check if we have it.
    $epoch_basis = null;
    $stat = @stat("/proc/{$pid_basis}");
    if ($stat !== false) {
      $epoch_basis = $stat['ctime'];
    }

    $tmp_dir = sys_get_temp_dir();

    $tmp_path = $tmp_dir.DIRECTORY_SEPARATOR.'phabricator-setup';
    if (!file_exists($tmp_path)) {
      @mkdir($tmp_path);
    }

    $is_ok = self::testTemporaryDirectory($tmp_path);
    if (!$is_ok) {
      $tmp_path = $tmp_dir;
      $is_ok = self::testTemporaryDirectory($tmp_path);
      if (!$is_ok) {
        // We can't find anywhere to write the cache, so just bail.
        return null;
      }
    }

    $tmp_name = 'setup-'.$pid_basis;
    if ($epoch_basis) {
      $tmp_name .= '.'.$epoch_basis;
    }
    $tmp_name .= '.cache';

    return $tmp_path.DIRECTORY_SEPARATOR.$tmp_name;
  }


  /**
   * @task setup
   */
  private static function testTemporaryDirectory($dir) {
    if (!@file_exists($dir)) {
      return false;
    }
    if (!@is_dir($dir)) {
      return false;
    }
    if (!@is_writable($dir)) {
      return false;
    }

    return true;
  }

  private static function addProfilerToCaches(array $caches) {
    foreach ($caches as $key => $cache) {
      $pcache = new PhutilKeyValueCacheProfiler($cache);
      $pcache->setProfiler(PhutilServiceProfiler::getInstance());
      $caches[$key] = $pcache;
    }
    return $caches;
  }

  private static function addNamespaceToCaches(array $caches) {
    $namespace = self::getNamespace();
    if (!$namespace) {
      return $caches;
    }

    foreach ($caches as $key => $cache) {
      $ncache = new PhutilKeyValueCacheNamespace($cache);
      $ncache->setNamespace($namespace);
      $caches[$key] = $ncache;
    }

    return $caches;
  }


  /**
   * Deflate a value, if deflation is available and has an impact.
   *
   * If the value is larger than 1KB, we have `gzdeflate()`, we successfully
   * can deflate it, and it benefits from deflation, we deflate it. Otherwise
   * we leave it as-is.
   *
   * Data can later be inflated with @{method:inflateData}.
   *
   * @param string String to attempt to deflate.
   * @return string|null Deflated string, or null if it was not deflated.
   * @task compress
   */
  public static function maybeDeflateData($value) {
    $len = strlen($value);
    if ($len <= 1024) {
      return null;
    }

    if (!function_exists('gzdeflate')) {
      return null;
    }

    $deflated = gzdeflate($value);
    if ($deflated === false) {
      return null;
    }

    $deflated_len = strlen($deflated);
    if ($deflated_len >= ($len / 2)) {
      return null;
    }

    return $deflated;
  }


  /**
   * Inflate data previously deflated by @{method:maybeDeflateData}.
   *
   * @param string Deflated data, from @{method:maybeDeflateData}.
   * @return string Original, uncompressed data.
   * @task compress
   */
  public static function inflateData($value) {
    if (!function_exists('gzinflate')) {
      throw new Exception(
        pht(
          '%s is not available; unable to read deflated data!',
          'gzinflate()'));
    }

    $value = gzinflate($value);
    if ($value === false) {
      throw new Exception(pht('Failed to inflate data!'));
    }

    return $value;
  }


}
