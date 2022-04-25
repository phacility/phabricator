<?php

abstract class AphrontBaseMySQLDatabaseConnection
  extends AphrontDatabaseConnection {

  private $configuration;
  private $connection;
  private $connectionPool = array();
  private $lastResult;

  private $nextError;

  const CALLERROR_QUERY = 777777;
  const CALLERROR_CONNECT = 777778;

  abstract protected function connect();
  abstract protected function rawQuery($raw_query);
  abstract protected function rawQueries(array $raw_queries);
  abstract protected function fetchAssoc($result);
  abstract protected function getErrorCode($connection);
  abstract protected function getErrorDescription($connection);
  abstract protected function closeConnection();
  abstract protected function freeResult($result);

  public function __construct(array $configuration) {
    $this->configuration = $configuration;
  }

  public function __clone() {
    $this->establishConnection();
  }

  public function openConnection() {
    $this->requireConnection();
  }

  public function close() {
    if ($this->lastResult) {
      $this->lastResult = null;
    }
    if ($this->connection) {
      $this->closeConnection();
      $this->connection = null;
    }
  }

  public function escapeColumnName($name) {
    return '`'.str_replace('`', '``', $name).'`';
  }


  public function escapeMultilineComment($comment) {
    // These can either terminate a comment, confuse the hell out of the parser,
    // make MySQL execute the comment as a query, or, in the case of semicolon,
    // are quasi-dangerous because the semicolon could turn a broken query into
    // a working query plus an ignored query.

    static $map = array(
      '--'  => '(DOUBLEDASH)',
      '*/'  => '(STARSLASH)',
      '//'  => '(SLASHSLASH)',
      '#'   => '(HASH)',
      '!'   => '(BANG)',
      ';'   => '(SEMICOLON)',
    );

    $comment = str_replace(
      array_keys($map),
      array_values($map),
      $comment);

    // For good measure, kill anything else that isn't a nice printable
    // character.
    $comment = preg_replace('/[^\x20-\x7F]+/', ' ', $comment);

    return '/* '.$comment.' */';
  }

  public function escapeStringForLikeClause($value) {
    $value = phutil_string_cast($value);
    $value = addcslashes($value, '\%_');
    $value = $this->escapeUTF8String($value);
    return $value;
  }

  protected function getConfiguration($key, $default = null) {
    return idx($this->configuration, $key, $default);
  }

  private function establishConnection() {
    $host = $this->getConfiguration('host');
    $database = $this->getConfiguration('database');

    $profiler = PhutilServiceProfiler::getInstance();
    $call_id = $profiler->beginServiceCall(
      array(
        'type'      => 'connect',
        'host'      => $host,
        'database'  => $database,
      ));

    // If we receive these errors, we'll retry the connection up to the
    // retry limit. For other errors, we'll fail immediately.
    $retry_codes = array(
      // "Connection Timeout"
      2002 => true,

      // "Unable to Connect"
      2003 => true,
    );

    $max_retries = max(1, $this->getConfiguration('retries', 3));
    for ($attempt = 1; $attempt <= $max_retries; $attempt++) {
      try {
        $conn = $this->connect();
        $profiler->endServiceCall($call_id, array());
        break;
      } catch (AphrontQueryException $ex) {
        $code = $ex->getCode();
        if (($attempt < $max_retries) && isset($retry_codes[$code])) {
          $message = pht(
            'Retrying database connection to "%s" after connection '.
            'failure (attempt %d; "%s"; error #%d): %s',
            $host,
            $attempt,
            get_class($ex),
            $code,
            $ex->getMessage());

          // See T13403. If we're silenced with the "@" operator, don't log
          // this connection attempt. This keeps things quiet if we're
          // running a setup workflow like "bin/config" and expect that the
          // database credentials will often be incorrect.

          if (error_reporting()) {
            phlog($message);
          }
        } else {
          $profiler->endServiceCall($call_id, array());
          throw $ex;
        }
      }
    }

    $this->connection = $conn;
  }

  protected function requireConnection() {
    if (!$this->connection) {
      if ($this->connectionPool) {
        $this->connection = array_pop($this->connectionPool);
      } else {
        $this->establishConnection();
      }
    }
    return $this->connection;
  }

  protected function beginAsyncConnection() {
    $connection = $this->requireConnection();
    $this->connection = null;
    return $connection;
  }

  protected function endAsyncConnection($connection) {
    if ($this->connection) {
      $this->connectionPool[] = $this->connection;
    }
    $this->connection = $connection;
  }

  public function selectAllResults() {
    $result = array();
    $res = $this->lastResult;
    if ($res == null) {
      throw new Exception(pht('No query result to fetch from!'));
    }
    while (($row = $this->fetchAssoc($res))) {
      $result[] = $row;
    }
    return $result;
  }

  public function executeQuery(PhutilQueryString $query) {
    $display_query = $query->getMaskedString();
    $raw_query = $query->getUnmaskedString();

    $this->lastResult = null;
    $retries = max(1, $this->getConfiguration('retries', 3));
    while ($retries--) {
      try {
        $this->requireConnection();
        $is_write = $this->checkWrite($raw_query);

        $profiler = PhutilServiceProfiler::getInstance();
        $call_id = $profiler->beginServiceCall(
          array(
            'type'    => 'query',
            'config'  => $this->configuration,
            'query'   => $display_query,
            'write'   => $is_write,
          ));

        $result = $this->rawQuery($raw_query);

        $profiler->endServiceCall($call_id, array());

        if ($this->nextError) {
          $result = null;
        }

        if ($result) {
          $this->lastResult = $result;
          break;
        }

        $this->throwQueryException($this->connection);
      } catch (AphrontConnectionLostQueryException $ex) {
        $can_retry = ($retries > 0);

        if ($this->isInsideTransaction()) {
          // Zero out the transaction state to prevent a second exception
          // ("program exited with open transaction") from being thrown, since
          // we're about to throw a more relevant/useful one instead.
          $state = $this->getTransactionState();
          while ($state->getDepth()) {
            $state->decreaseDepth();
          }

          $can_retry = false;
        }

        if ($this->isHoldingAnyLock()) {
          $this->forgetAllLocks();
          $can_retry = false;
        }

        $this->close();

        if (!$can_retry) {
          throw $ex;
        }
      }
    }
  }

  public function executeRawQueries(array $raw_queries) {
    if (!$raw_queries) {
      return array();
    }

    $is_write = false;
    foreach ($raw_queries as $key => $raw_query) {
      $is_write = $is_write || $this->checkWrite($raw_query);
      $raw_queries[$key] = rtrim($raw_query, "\r\n\t ;");
    }

    $profiler = PhutilServiceProfiler::getInstance();
    $call_id = $profiler->beginServiceCall(
      array(
        'type'    => 'multi-query',
        'config'  => $this->configuration,
        'queries' => $raw_queries,
        'write'   => $is_write,
      ));

    $results = $this->rawQueries($raw_queries);

    $profiler->endServiceCall($call_id, array());

    return $results;
  }

  protected function processResult($result) {
    if (!$result) {
      try {
        $this->throwQueryException($this->requireConnection());
      } catch (Exception $ex) {
        return $ex;
      }
    } else if (is_bool($result)) {
      return $this->getAffectedRows();
    }
    $rows = array();
    while (($row = $this->fetchAssoc($result))) {
      $rows[] = $row;
    }
    $this->freeResult($result);
    return $rows;
  }

  protected function checkWrite($raw_query) {
    // NOTE: The opening "(" allows queries in the form of:
    //
    //   (SELECT ...) UNION (SELECT ...)
    $is_write = !preg_match('/^[(]*(SELECT|SHOW|EXPLAIN)\s/', $raw_query);
    if ($is_write) {
      if ($this->getReadOnly()) {
        throw new Exception(
          pht(
            'Attempting to issue a write query on a read-only '.
            'connection (to database "%s")!',
            $this->getConfiguration('database')));
      }
      AphrontWriteGuard::willWrite();
      return true;
    }

    return false;
  }

  protected function throwQueryException($connection) {
    if ($this->nextError) {
      $errno = $this->nextError;
      $error = pht('Simulated error.');
      $this->nextError = null;
    } else {
      $errno = $this->getErrorCode($connection);
      $error = $this->getErrorDescription($connection);
    }
    $this->throwQueryCodeException($errno, $error);
  }

  private function throwCommonException($errno, $error) {
    $message = pht('#%d: %s', $errno, $error);

    switch ($errno) {
      case 2013: // Connection Dropped
        throw new AphrontConnectionLostQueryException($message);
      case 2006: // Gone Away
        $more = pht(
          'This error may occur if your configured MySQL "wait_timeout" or '.
          '"max_allowed_packet" values are too small. This may also indicate '.
          'that something used the MySQL "KILL <process>" command to kill '.
          'the connection running the query.');
        throw new AphrontConnectionLostQueryException("{$message}\n\n{$more}");
      case 1213: // Deadlock
        throw new AphrontDeadlockQueryException($message);
      case 1205: // Lock wait timeout exceeded
        throw new AphrontLockTimeoutQueryException($message);
      case 1062: // Duplicate Key
        // NOTE: In some versions of MySQL we get a key name back here, but
        // older versions just give us a key index ("key 2") so it's not
        // portable to parse the key out of the error and attach it to the
        // exception.
        throw new AphrontDuplicateKeyQueryException($message);
      case 1044: // Access denied to database
      case 1142: // Access denied to table
      case 1143: // Access denied to column
      case 1227: // Access denied (e.g., no SUPER for SHOW SLAVE STATUS).

        // See T13622. Try to help users figure out that this is a GRANT
        // problem.

        $more = pht(
          'This error usually indicates that you need to "GRANT" the '.
          'MySQL user additional permissions. See "GRANT" in the MySQL '.
          'manual for help.');

        throw new AphrontAccessDeniedQueryException("{$message}\n\n{$more}");
      case 1045: // Access denied (auth)
        throw new AphrontInvalidCredentialsQueryException($message);
      case 1146: // No such table
      case 1049: // No such database
      case 1054: // Unknown column "..." in field list
        throw new AphrontSchemaQueryException($message);
    }

    // TODO: 1064 is syntax error, and quite terrible in production.

    return null;
  }

  protected function throwConnectionException($errno, $error, $user, $host) {
    $this->throwCommonException($errno, $error);

    $message = pht(
      'Attempt to connect to %s@%s failed with error #%d: %s.',
      $user,
      $host,
      $errno,
      $error);

    throw new AphrontConnectionQueryException($message, $errno);
  }


  protected function throwQueryCodeException($errno, $error) {
    $this->throwCommonException($errno, $error);

    $message = pht(
      '#%d: %s',
      $errno,
      $error);

    throw new AphrontQueryException($message, $errno);
  }

  /**
   * Force the next query to fail with a simulated error. This should be used
   * ONLY for unit tests.
   */
  public function simulateErrorOnNextQuery($error) {
    $this->nextError = $error;
    return $this;
  }

  /**
   * Check inserts for characters outside of the BMP. Even with the strictest
   * settings, MySQL will silently truncate data when it encounters these, which
   * can lead to data loss and security problems.
   */
  protected function validateUTF8String($string) {
    if (phutil_is_utf8($string)) {
      return;
    }

    throw new AphrontCharacterSetQueryException(
      pht(
        'Attempting to construct a query using a non-utf8 string when '.
        'utf8 is expected. Use the `%%B` conversion to escape binary '.
        'strings data.'));
  }

}
