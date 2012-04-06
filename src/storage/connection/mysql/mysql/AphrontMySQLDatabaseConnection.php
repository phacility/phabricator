<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * @group storage
 */
final class AphrontMySQLDatabaseConnection
  extends AphrontMySQLDatabaseConnectionBase {

  public function escapeString($string) {
    return mysql_real_escape_string($string, $this->requireConnection());
  }

  public function getInsertID() {
    return mysql_insert_id($this->requireConnection());
  }

  public function getAffectedRows() {
    return mysql_affected_rows($this->requireConnection());
  }

  protected function getTransactionKey() {
    return (int)$this->requireConnection();
  }

  protected function connect() {
    if (!function_exists('mysql_connect')) {
      // We have to '@' the actual call since it can spew all sorts of silly
      // noise, but it will also silence fatals caused by not having MySQL
      // installed, which has bitten me on three separate occasions. Make sure
      // such failures are explicit and loud.
      throw new Exception(
        "About to call mysql_connect(), but the PHP MySQL extension is not ".
        "available!");
    }

    $user = $this->getConfiguration('user');
    $host = $this->getConfiguration('host');
    $database = $this->getConfiguration('database');

    $conn = @mysql_connect(
      $host,
      $user,
      $this->getConfiguration('pass'),
      $new_link = true,
      $flags = 0);

    if (!$conn) {
      $errno = mysql_errno();
      $error = mysql_error();
      throw new AphrontQueryConnectionException(
        "Attempt to connect to {$user}@{$host} failed with error ".
        "#{$errno}: {$error}.", $errno);
    }

    if ($database !== null) {
      $ret = @mysql_select_db($database, $conn);
      if (!$ret) {
        $this->throwQueryException($conn);
      }
    }

    mysql_set_charset('utf8', $conn);

    return $conn;
  }

  protected function rawQuery($raw_query) {
    return @mysql_query($raw_query, $this->requireConnection());
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
