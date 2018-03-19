<?php

/**
 * Global, MySQL-backed lock. This is a high-reliability, low-performance
 * global lock.
 *
 * The lock is maintained by using GET_LOCK() in MySQL, and automatically
 * released when the connection terminates. Thus, this lock can safely be used
 * to control access to shared resources without implementing any sort of
 * timeout or override logic: the lock can't normally be stuck in a locked state
 * with no process actually holding the lock.
 *
 * However, acquiring the lock is moderately expensive (several network
 * roundtrips). This makes it unsuitable for tasks where lock performance is
 * important.
 *
 *    $lock = PhabricatorGlobalLock::newLock('example');
 *    $lock->lock();
 *      do_contentious_things();
 *    $lock->unlock();
 *
 * NOTE: This lock is not completely global; it is namespaced to the active
 * storage namespace so that unit tests running in separate table namespaces
 * are isolated from one another.
 *
 * @task construct  Constructing Locks
 * @task impl       Implementation
 */
final class PhabricatorGlobalLock extends PhutilLock {

  private $parameters;
  private $conn;
  private $isExternalConnection = false;
  private $log;
  private $disableLogging;

  private static $pool = array();


/* -(  Constructing Locks  )------------------------------------------------- */


  public static function newLock($name, $parameters = array()) {
    $namespace = PhabricatorLiskDAO::getStorageNamespace();
    $namespace = PhabricatorHash::digestToLength($namespace, 20);

    $parts = array();
    ksort($parameters);
    foreach ($parameters as $key => $parameter) {
      if (!preg_match('/^[a-zA-Z0-9]+\z/', $key)) {
        throw new Exception(
          pht(
            'Lock parameter key "%s" must be alphanumeric.',
            $key));
      }

      if (!is_scalar($parameter) && !is_null($parameter)) {
        throw new Exception(
          pht(
            'Lock parameter for key "%s" must be a scalar.',
            $key));
      }

      $value = phutil_json_encode($parameter);
      $parts[] = "{$key}={$value}";
    }
    $parts = implode(', ', $parts);

    $local = "{$name}({$parts})";
    $local = PhabricatorHash::digestToLength($local, 20);

    $full_name = "ph:{$namespace}:{$local}";
    $lock = self::getLock($full_name);
    if (!$lock) {
      $lock = new PhabricatorGlobalLock($full_name);
      self::registerLock($lock);

      $lock->parameters = $parameters;
    }

    return $lock;
  }

  /**
   * Use a specific database connection for locking.
   *
   * By default, `PhabricatorGlobalLock` will lock on the "repository" database
   * (somewhat arbitrarily). In most cases this is fine, but this method can
   * be used to lock on a specific connection.
   *
   * @param  AphrontDatabaseConnection
   * @return this
   */
  public function useSpecificConnection(AphrontDatabaseConnection $conn) {
    $this->conn = $conn;
    $this->isExternalConnection = true;
    return $this;
  }

  public function setDisableLogging($disable) {
    $this->disableLogging = $disable;
    return $this;
  }


/* -(  Implementation  )----------------------------------------------------- */

  protected function doLock($wait) {
    $conn = $this->conn;

    if (!$conn) {
      // Try to reuse a connection from the connection pool.
      $conn = array_pop(self::$pool);
    }

    if (!$conn) {
      // NOTE: Using the 'repository' database somewhat arbitrarily, mostly
      // because the first client of locks is the repository daemons. We must
      // always use the same database for all locks, but don't access any
      // tables so we could use any valid database. We could build a
      // database-free connection instead, but that's kind of messy and we
      // might forget about it in the future if we vertically partition the
      // application.
      $dao = new PhabricatorRepository();

      // NOTE: Using "force_new" to make sure each lock is on its own
      // connection.
      $conn = $dao->establishConnection('w', $force_new = true);
    }

    // NOTE: Since MySQL will disconnect us if we're idle for too long, we set
    // the wait_timeout to an enormous value, to allow us to hold the
    // connection open indefinitely (or, at least, for 24 days).
    $max_allowed_timeout = 2147483;
    queryfx($conn, 'SET wait_timeout = %d', $max_allowed_timeout);

    $lock_name = $this->getName();

    $result = queryfx_one(
      $conn,
      'SELECT GET_LOCK(%s, %f)',
      $lock_name,
      $wait);

    $ok = head($result);
    if (!$ok) {
      throw new PhutilLockException($lock_name);
    }

    $conn->rememberLock($lock_name);

    $this->conn = $conn;

    if ($this->shouldLogLock()) {
      global $argv;

      $lock_context = array(
        'pid' => getmypid(),
        'host' => php_uname('n'),
        'argv' => $argv,
      );

      $log = id(new PhabricatorDaemonLockLog())
        ->setLockName($lock_name)
        ->setLockParameters($this->parameters)
        ->setLockContext($lock_context)
        ->save();

      $this->log = $log;
    }
  }

  protected function doUnlock() {
    $lock_name = $this->getName();

    $conn = $this->conn;

    try {
      $result = queryfx_one(
        $conn,
        'SELECT RELEASE_LOCK(%s)',
        $lock_name);
      $conn->forgetLock($lock_name);
    } catch (Exception $ex) {
      $result = array(null);
    }

    $ok = head($result);
    if (!$ok) {
      // TODO: We could throw here, but then this lock doesn't get marked
      // unlocked and we throw again later when exiting. It also doesn't
      // particularly matter for any current applications. For now, just
      // swallow the error.
    }

    $this->conn = null;
    $this->isExternalConnection = false;

    if (!$this->isExternalConnection) {
      $conn->close();
      self::$pool[] = $conn;
    }

    if ($this->log) {
      $log = $this->log;
      $this->log = null;

      $conn = $log->establishConnection('w');
      queryfx(
        $conn,
        'UPDATE %T SET lockReleased = UNIX_TIMESTAMP() WHERE id = %d',
        $log->getTableName(),
        $log->getID());
    }
  }

  private function shouldLogLock() {
    if ($this->disableLogging) {
      return false;
    }

    $policy = id(new PhabricatorDaemonLockLogGarbageCollector())
      ->getRetentionPolicy();
    if (!$policy) {
      return false;
    }

    return true;
  }

}
