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
 * @task  xaction Transaction Management
 * @group storage
 */
abstract class AphrontDatabaseConnection {

  private $transactionState;

  abstract public function getInsertID();
  abstract public function getAffectedRows();
  abstract public function selectAllResults();
  abstract public function executeRawQuery($raw_query);

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


/* -(  Transaction Management  )--------------------------------------------- */


  /**
   * Begin a transaction, or set a savepoint if the connection is already
   * transactional.
   *
   * @return this
   * @task xaction
   */
  public function openTransaction() {
    $state = $this->getTransactionState();
    $point = $state->getSavepointName();
    $depth = $state->increaseDepth();

    $new_transaction = ($depth == 1);
    if ($new_transaction) {
      $this->query('START TRANSACTION');
    } else {
      $this->query('SAVEPOINT '.$point);
    }

    return $this;
  }


  /**
   * Commit a transaction, or stage a savepoint for commit once the entire
   * transaction completes if inside a transaction stack.
   *
   * @return this
   * @task xaction
   */
  public function saveTransaction() {
    $state = $this->getTransactionState();
    $depth = $state->decreaseDepth();

    if ($depth == 0) {
      $this->query('COMMIT');
    }

    return $this;
  }


  /**
   * Rollback a transaction, or unstage the last savepoint if inside a
   * transaction stack.
   *
   * @return this
   */
  public function killTransaction() {
    $state = $this->getTransactionState();
    $depth = $state->decreaseDepth();

    if ($depth == 0) {
      $this->query('ROLLBACK');
    } else {
      $this->query('ROLLBACK TO SAVEPOINT '.$state->getSavepointName());
    }

    return $this;
  }


  /**
   * Returns true if the connection is transactional.
   *
   * @return bool True if the connection is currently transactional.
   * @task xaction
   */
  public function isInsideTransaction() {
    $state = $this->getTransactionState();
    return ($state->getDepth() > 0);
  }


  /**
   * Get the current @{class:AphrontDatabaseTransactionState} object, or create
   * one if none exists.
   *
   * @return AphrontDatabaseTransactionState Current transaction state.
   * @task xaction
   */
  protected function getTransactionState() {
    if (!$this->transactionState) {
      $this->transactionState = new AphrontDatabaseTransactionState();
    }
    return $this->transactionState;
  }


  /**
   * @task xaction
   */
  public function beginReadLocking() {
    $this->getTransactionState()->beginReadLocking();
    return $this;
  }


  /**
   * @task xaction
   */
  public function endReadLocking() {
    $this->getTransactionState()->endReadLocking();
    return $this;
  }


  /**
   * @task xaction
   */
  public function isReadLocking() {
    return $this->getTransactionState()->isReadLocking();
  }


  /**
   * @task xaction
   */
  public function beginWriteLocking() {
    $this->getTransactionState()->beginWriteLocking();
    return $this;
  }


  /**
   * @task xaction
   */
  public function endWriteLocking() {
    $this->getTransactionState()->endWriteLocking();
    return $this;
  }


  /**
   * @task xaction
   */
  public function isWriteLocking() {
    return $this->getTransactionState()->isWriteLocking();
  }

}
