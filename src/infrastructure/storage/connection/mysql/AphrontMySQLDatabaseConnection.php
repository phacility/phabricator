<?php

final class AphrontMySQLDatabaseConnection
  extends AphrontBaseMySQLDatabaseConnection {

  public function escapeUTF8String($string) {
    $this->validateUTF8String($string);
    return $this->escapeBinaryString($string);
  }

  public function escapeBinaryString($string) {
    return mysql_real_escape_string($string, $this->requireConnection());
  }

  public function getInsertID() {
    return mysql_insert_id($this->requireConnection());
  }

  public function getAffectedRows() {
    return mysql_affected_rows($this->requireConnection());
  }

  protected function closeConnection() {
    mysql_close($this->requireConnection());
  }

  protected function connect() {
    if (!function_exists('mysql_connect')) {
      // We have to '@' the actual call since it can spew all sorts of silly
      // noise, but it will also silence fatals caused by not having MySQL
      // installed, which has bitten me on three separate occasions. Make sure
      // such failures are explicit and loud.
      throw new Exception(
        pht(
          'About to call %s, but the PHP MySQL extension is not available!',
          'mysql_connect()'));
    }

    $user = $this->getConfiguration('user');
    $host = $this->getConfiguration('host');
    $port = $this->getConfiguration('port');

    if ($port) {
      $host .= ':'.$port;
    }

    $database = $this->getConfiguration('database');

    $pass = $this->getConfiguration('pass');
    if ($pass instanceof PhutilOpaqueEnvelope) {
      $pass = $pass->openEnvelope();
    }

    $timeout = $this->getConfiguration('timeout');
    $timeout_ini = 'mysql.connect_timeout';
    if ($timeout) {
      $old_timeout = ini_get($timeout_ini);
      ini_set($timeout_ini, $timeout);
    }

    try {
      $conn = @mysql_connect(
        $host,
        $user,
        $pass,
        $new_link = true,
        $flags = 0);
    } catch (Exception $ex) {
      if ($timeout) {
        ini_set($timeout_ini, $old_timeout);
      }
      throw $ex;
    }

    if ($timeout) {
      ini_set($timeout_ini, $old_timeout);
    }

    if (!$conn) {
      $errno = mysql_errno();
      $error = mysql_error();
      $this->throwConnectionException($errno, $error, $user, $host);
    }

    if ($database !== null) {
      $ret = @mysql_select_db($database, $conn);
      if (!$ret) {
        $this->throwQueryException($conn);
      }
    }

    $ok = @mysql_set_charset('utf8mb4', $conn);
    if (!$ok) {
      mysql_set_charset('binary', $conn);
    }

    return $conn;
  }

  protected function rawQuery($raw_query) {
    return @mysql_query($raw_query, $this->requireConnection());
  }

  /**
   * @phutil-external-symbol function mysql_multi_query
   * @phutil-external-symbol function mysql_fetch_result
   * @phutil-external-symbol function mysql_more_results
   * @phutil-external-symbol function mysql_next_result
   */
  protected function rawQueries(array $raw_queries) {
    $conn = $this->requireConnection();
    $results = array();

    if (!function_exists('mysql_multi_query')) {
      foreach ($raw_queries as $key => $raw_query) {
        $results[$key] = $this->processResult($this->rawQuery($raw_query));
      }
      return $results;
    }

    if (!mysql_multi_query(implode("\n;\n\n", $raw_queries), $conn)) {
      $ex = $this->processResult(false);
      return array_fill_keys(array_keys($raw_queries), $ex);
    }

    $processed_all = false;
    foreach ($raw_queries as $key => $raw_query) {
      $results[$key] = $this->processResult(@mysql_fetch_result($conn));
      if (!mysql_more_results($conn)) {
        $processed_all = true;
        break;
      }
      mysql_next_result($conn);
    }

    if (!$processed_all) {
      throw new Exception(
        pht('There are some results left in the result set.'));
    }

    return $results;
  }

  protected function freeResult($result) {
    mysql_free_result($result);
  }

  public function supportsParallelQueries() {
    // fb_parallel_query() doesn't support results with different columns.
    return false;
  }

  /**
   * @phutil-external-symbol function fb_parallel_query
   */
  public function executeParallelQueries(
    array $queries,
    array $conns = array()) {
    assert_instances_of($conns, __CLASS__);

    $map = array();
    $is_write = false;
    foreach ($queries as $id => $query) {
      $is_write = $is_write || $this->checkWrite($query);
      $conn = idx($conns, $id, $this);

      $host = $conn->getConfiguration('host');
      $port = 0;
      $match = null;
      if (preg_match('/(.+):(.+)/', $host, $match)) {
        list(, $host, $port) = $match;
      }

      $pass = $conn->getConfiguration('pass');
      if ($pass instanceof PhutilOpaqueEnvelope) {
        $pass = $pass->openEnvelope();
      }

      $map[$id] = array(
        'sql' => $query,
        'ip' => $host,
        'port' => $port,
        'username' => $conn->getConfiguration('user'),
        'password' => $pass,
        'db' => $conn->getConfiguration('database'),
      );
    }

    $profiler = PhutilServiceProfiler::getInstance();
    $call_id = $profiler->beginServiceCall(
      array(
        'type'    => 'multi-query',
        'queries' => $queries,
        'write'   => $is_write,
      ));

    $map = fb_parallel_query($map);

    $profiler->endServiceCall($call_id, array());

    $results = array();
    $pos = 0;
    $err_pos = 0;
    foreach ($queries as $id => $query) {
      $errno = idx(idx($map, 'errno', array()), $err_pos);
      $err_pos++;
      if ($errno) {
        try {
          $this->throwQueryCodeException($errno, $map['error'][$id]);
        } catch (Exception $ex) {
          $results[$id] = $ex;
        }
        continue;
      }
      $results[$id] = $map['result'][$pos];
      $pos++;
    }
    return $results;
  }

  protected function fetchAssoc($result) {
    return mysql_fetch_assoc($result);
  }

  protected function getErrorCode($connection) {
    return mysql_errno($connection);
  }

  protected function getErrorDescription($connection) {
    return mysql_error($connection);
  }

}
