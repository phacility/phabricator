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
 *
 * @phutil-external-symbol class mysqli
 */
final class AphrontMySQLiDatabaseConnection
  extends AphrontMySQLDatabaseConnectionBase {

  public function escapeString($string) {
    return $this->requireConnection()->escape_string($string);
  }

  public function getInsertID() {
    return $this->requireConnection()->insert_id;
  }

  public function getAffectedRows() {
    return $this->requireConnection()->affected_rows;
  }

  protected function getTransactionKey() {
    return spl_object_hash($this->requireConnection());
  }

  protected function connect() {
    if (!class_exists('mysqli', false)) {
      throw new Exception(
        "About to call new mysqli(), but the PHP MySQLi extension is not ".
        "available!");
    }

    $user = $this->getConfiguration('user');
    $host = $this->getConfiguration('host');
    $database = $this->getConfiguration('database');

    $conn = @new mysqli(
      $host,
      $user,
      $this->getConfiguration('pass'),
      $database);

    $errno = $conn->connect_errno;
    if ($errno) {
      $error = $conn->connect_error;
      throw new AphrontQueryConnectionException(
        "Attempt to connect to {$user}@{$host} failed with error ".
        "#{$errno}: {$error}.", $errno);
    }

    $conn->set_charset('utf8');

    return $conn;
  }

  protected function rawQuery($raw_query) {
    return @$this->requireConnection()->query($raw_query);
  }

  protected function fetchAssoc($result) {
    return $result->fetch_assoc();
  }

  protected function getErrorCode($connection) {
    return $connection->errno;
  }

  protected function getErrorDescription($connection) {
    return $connection->error;
  }

}
