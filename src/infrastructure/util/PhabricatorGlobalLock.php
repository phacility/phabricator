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

  private $conn;

  private static $pool = array();


/* -(  Constructing Locks  )------------------------------------------------- */


  public static function newLock($name) {
    $namespace = PhabricatorLiskDAO::getStorageNamespace();
    $namespace = PhabricatorHash::digestToLength($namespace, 20);

    $full_name = 'ph:'.$namespace.':'.$name;

    $length_limit = 64;
    if (strlen($full_name) > $length_limit) {
      throw new Exception(
        pht(
          'Lock name "%s" is too long (full lock name is "%s"). The '.
          'full lock name must not be longer than %s bytes.',
          $name,
          $full_name,
          new PhutilNumber($length_limit)));
    }

    $lock = self::getLock($full_name);
    if (!$lock) {
      $lock = new PhabricatorGlobalLock($full_name);
      self::registerLock($lock);
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

    $result = queryfx_one(
      $conn,
      'SELECT GET_LOCK(%s, %f)',
      $this->getName(),
      $wait);

    $ok = head($result);
    if (!$ok) {
      throw new PhutilLockException($this->getName());
    }

    $this->conn = $conn;
  }

  protected function doUnlock() {
    queryfx(
      $this->conn,
      'SELECT RELEASE_LOCK(%s)',
      $this->getName());

    $this->conn->close();
    self::$pool[] = $this->conn;
    $this->conn = null;
  }

}
