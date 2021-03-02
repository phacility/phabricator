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
  private $externalConnection;
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
  public function setExternalConnection(AphrontDatabaseConnection $conn) {
    if ($this->conn) {
      throw new Exception(
        pht(
          'Lock is already held, and must be released before the '.
          'connection may be changed.'));
    }
    $this->externalConnection = $conn;
    return $this;
  }

  public function setDisableLogging($disable) {
    $this->disableLogging = $disable;
    return $this;
  }


/* -(  Connection Pool  )---------------------------------------------------- */

  public static function getConnectionPoolSize() {
    return count(self::$pool);
  }

  public static function clearConnectionPool() {
    self::$pool = array();
  }

  public static function newConnection() {
    // NOTE: Use of the "repository" database is somewhat arbitrary, mostly
    // because the first client of locks was the repository daemons.

    // We must always use the same database for all locks, because different
    // databases may be on different hosts if the database is partitioned.

    // However, we don't access any tables so we could use any valid database.
    // We could build a database-free connection instead, but that's kind of
    // messy and unusual.

    $dao = new PhabricatorRepository();

    // NOTE: Using "force_new" to make sure each lock is on its own connection.

    // See T13627. This is critically important in versions of MySQL older
    // than MySQL 5.7, because they can not hold more than one lock per
    // connection simultaneously.

    return $dao->establishConnection('w', $force_new = true);
  }

/* -(  Implementation  )----------------------------------------------------- */

  protected function doLock($wait) {
    $conn = $this->conn;

    if (!$conn) {
      if ($this->externalConnection) {
        $conn = $this->externalConnection;
      }
    }

    if (!$conn) {
      // Try to reuse a connection from the connection pool.
      $conn = array_pop(self::$pool);
    }

    if (!$conn) {
      $conn = self::newConnection();
    }

    // See T13627. We must never hold more than one lock per connection, so
    // make sure this connection has no existing locks. (Normally, we should
    // only be able to get here if callers explicitly provide the same external
    // connection to multiple locks.)

    if ($conn->isHoldingAnyLock()) {
      throw new Exception(
        pht(
          'Unable to establish lock on connection: this connection is '.
          'already holding a lock. Acquiring a second lock on the same '.
          'connection would release the first lock in MySQL versions '.
          'older than 5.7.'));
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

      // See PHI1794. We failed to acquire the lock, but the connection itself
      // is still good. We're done with it, so add it to the pool, just as we
      // would if we were releasing the lock.

      // If we don't do this, we may establish a huge number of connections
      // very rapidly if many workers try to acquire a lock at once. For
      // example, this can happen if there are a large number of webhook tasks
      // in the queue.

      // See T13627. If this is an external connection, don't put it into
      // the shared connection pool.

      if (!$this->externalConnection) {
        self::$pool[] = $conn;
      }

      throw id(new PhutilLockException($lock_name))
        ->setHint($this->newHint($lock_name, $wait));
    }

    $conn->rememberLock($lock_name);

    $this->conn = $conn;

    if ($this->shouldLogLock()) {
      $lock_context = $this->newLockContext();

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

    if (!$this->externalConnection) {
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

  private function newLockContext() {
    $context = array(
      'pid' => getmypid(),
      'host' => php_uname('n'),
      'sapi' => php_sapi_name(),
    );

    global $argv;
    if ($argv) {
      $context['argv'] = $argv;
    }

    $access_log = null;

    // TODO: There's currently no cohesive way to get the parameterized access
    // log for the current request across different request types. Web requests
    // have an "AccessLog", SSH requests have an "SSHLog", and other processes
    // (like scripts) have no log. But there's no method to say "give me any
    // log you've got". For now, just test if we have a web request and use the
    // "AccessLog" if we do, since that's the only one we actually read any
    // parameters from.

    // NOTE: "PhabricatorStartup" is only available from web requests, not
    // from CLI scripts.
    if (class_exists('PhabricatorStartup', false)) {
      $access_log = PhabricatorAccessLog::getLog();
    }

    if ($access_log) {
      $controller = $access_log->getData('C');
      if ($controller) {
        $context['controller'] = $controller;
      }

      $method = $access_log->getData('m');
      if ($method) {
        $context['method'] = $method;
      }
    }

    return $context;
  }

  private function newHint($lock_name, $wait) {
    if (!$this->shouldLogLock()) {
      return pht(
        'Enable the lock log for more detailed information about '.
        'which process is holding this lock.');
    }

    $now = PhabricatorTime::getNow();

    // First, look for recent logs. If other processes have been acquiring and
    // releasing this lock while we've been waiting, this is more likely to be
    // a contention/throughput issue than an issue with something hung while
    // holding the lock.
    $limit = 100;
    $logs = id(new PhabricatorDaemonLockLog())->loadAllWhere(
      'lockName = %s AND dateCreated >= %d ORDER BY id ASC LIMIT %d',
      $lock_name,
      ($now - $wait),
      $limit);

    if ($logs) {
      if (count($logs) === $limit) {
        return pht(
          'During the last %s second(s) spent waiting for the lock, more '.
          'than %s other process(es) acquired it, so this is likely a '.
          'bottleneck. Use "bin/lock log --name %s" to review log activity.',
          new PhutilNumber($wait),
          new PhutilNumber($limit),
          $lock_name);
      } else {
        return pht(
          'During the last %s second(s) spent waiting for the lock, %s '.
          'other process(es) acquired it, so this is likely a '.
          'bottleneck. Use "bin/lock log --name %s" to review log activity.',
          new PhutilNumber($wait),
          phutil_count($logs),
          $lock_name);
      }
    }

    $last_log = id(new PhabricatorDaemonLockLog())->loadOneWhere(
      'lockName = %s ORDER BY id DESC LIMIT 1',
      $lock_name);

    if ($last_log) {
      $info = array();

      $acquired = $last_log->getDateCreated();
      $context = $last_log->getLockContext();

      $process_info = array();

      $pid = idx($context, 'pid');
      if ($pid) {
        $process_info[] = 'pid='.$pid;
      }

      $host = idx($context, 'host');
      if ($host) {
        $process_info[] = 'host='.$host;
      }

      $sapi = idx($context, 'sapi');
      if ($sapi) {
        $process_info[] = 'sapi='.$sapi;
      }

      $argv = idx($context, 'argv');
      if ($argv) {
        $process_info[] = 'argv='.(string)csprintf('%LR', $argv);
      }

      $controller = idx($context, 'controller');
      if ($controller) {
        $process_info[] = 'controller='.$controller;
      }

      $method = idx($context, 'method');
      if ($method) {
        $process_info[] = 'method='.$method;
      }

      $process_info = implode(', ', $process_info);

      $info[] = pht(
        'This lock was most recently acquired by a process (%s) '.
        '%s second(s) ago.',
        $process_info,
        new PhutilNumber($now - $acquired));

      $released = $last_log->getLockReleased();
      if ($released) {
        $info[] = pht(
          'This lock was released %s second(s) ago.',
          new PhutilNumber($now - $released));
      } else {
        $info[] = pht('There is no record of this lock being released.');
      }

      return implode(' ', $info);
    }

    return pht(
      'Found no records of processes acquiring or releasing this lock.');
  }

}
