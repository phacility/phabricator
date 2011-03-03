<?php

/*
 * Copyright 2011 Facebook, Inc.
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
abstract class AphrontDatabaseConnection {

  private static $transactionStacks             = array();
  private static $transactionShutdownRegistered = false;

  abstract public function getInsertID();
  abstract public function getAffectedRows();
  abstract public function selectAllResults();
  abstract public function executeRawQuery($raw_query);
  abstract protected function getTransactionKey();

  abstract public function escapeString($string);
  abstract public function escapeColumnName($string);
  abstract public function escapeMultilineComment($string);
  abstract public function escapeStringForLikeClause($string);

  public function queryData($pattern/*, $arg, $arg, ... */) {
    $args = func_get_args();
    array_unshift($args, $this);
    return call_user_func_array('queryfx_all', $args);
  }

  public function query($pattern/*, $arg, $arg, ... */) {
    $args = func_get_args();
    array_unshift($args, $this);
    return call_user_func_array('queryfx', $args);
  }

  // TODO: Probably need to reset these when we catch a connection exception
  // in the transaction stack.
  protected function &getLockLevels() {
    static $levels = array();
    $key = $this->getTransactionKey();
    if (!isset($levels[$key])) {
      $levels[$key] = array(
        'read'  => 0,
        'write' => 0,
      );
    }

    return $levels[$key];
  }

  public function isReadLocking() {
    $levels = &$this->getLockLevels();
    return ($levels['read'] > 0);
  }

  public function isWriteLocking() {
    $levels = &$this->getLockLevels();
    return ($levels['write'] > 0);
  }

  public function startReadLocking() {
    $levels = &$this->getLockLevels();
    ++$levels['read'];
    return $this;
  }

  public function startWriteLocking() {
    $levels = &$this->getLockLevels();
    ++$levels['write'];
    return $this;
  }

  public function stopReadLocking() {
    $levels = &$this->getLockLevels();
    if ($levels['read'] < 1) {
      throw new Exception('Unable to stop read locking: not read locking.');
    }
    --$levels['read'];
    return $this;
  }

  public function stopWriteLocking() {
    $levels = &$this->getLockLevels();
    if ($levels['write'] < 1) {
      throw new Exception('Unable to stop read locking: not write locking.');
    }
    --$levels['write'];
    return $this;
  }

  protected function &getTransactionStack($key) {
    if (!self::$transactionShutdownRegistered) {
      self::$transactionShutdownRegistered = true;
      register_shutdown_function(
        array(
          'AphrontDatabaseConnection',
          'shutdownTransactionStacks',
        ));
    }

    if (!isset(self::$transactionStacks[$key])) {
      self::$transactionStacks[$key] = array();
    }

    return self::$transactionStacks[$key];
  }

  public static function shutdownTransactionStacks() {
    foreach (self::$transactionStacks as $stack) {
      if ($stack === false) {
        continue;
      }

      $count = count($stack);
      if ($count) {
        throw new Exception(
          'Script exited with '.$count.' open transactions! The '.
          'transactions will be implicitly rolled back. Calls to '.
          'openTransaction() should always be paired with a call to '.
          'saveTransaction() or killTransaction(); you have an unpaired '.
          'call somewhere.',
          $count);
      }
    }
  }

  public function openTransaction() {
    $key = $this->getTransactionKey();
    $stack = &$this->getTransactionStack($key);

    $new_transaction = !count($stack);

    // TODO: At least in development, push context information instead of
    // `true' so we can report (or, at least, guess) where unpaired
    // transaction calls happened.
    $stack[] = true;

    end($stack);
    $key = key($stack);

    if ($new_transaction) {
      $this->query('START TRANSACTION');
    } else {
      $this->query('SAVEPOINT '.$this->getSavepointName($key));
    }
  }

  public function isInsideTransaction() {
    $key = $this->getTransactionKey();
    $stack = &$this->getTransactionStack($key);
    return (bool)count($stack);
  }

  public function saveTransaction() {
    $key = $this->getTransactionKey();
    $stack = &$this->getTransactionStack($key);

    if (!count($stack)) {
      throw new Exception(
        "No open transaction! Unable to save transaction, since there ".
        "isn't one.");
    }

    array_pop($stack);

    if (!count($stack)) {
      $this->query('COMMIT');
    }
  }

  public function saveTransactionUnless($cond) {
    if ($cond) {
      $this->killTransaction();
    } else {
      $this->saveTransaction();
    }
  }

  public function saveTransactionIf($cond) {
    $this->saveTransactionUnless(!$cond);
  }

  public function killTransaction() {
    $key = $this->getTransactionKey();
    $stack = &$this->getTransactionStack($key);

    if (!count($stack)) {
      throw new Exception(
        "No open transaction! Unable to kill transaction, since there ".
        "isn't one.");
    }

    $count = count($stack);

    end($stack);
    $key = key($stack);
    array_pop($stack);

    if (!count($stack)) {
      $this->query('ROLLBACK');
    } else {
      $this->query(
         'ROLLBACK TO SAVEPOINT '.$this->getSavepointName($key)
        );
    }
  }

  protected function getSavepointName($key) {
    return 'LiskSavepoint_'.$key;
  }
}
