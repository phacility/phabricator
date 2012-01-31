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
 * Represents current transaction state of a connection.
 *
 * @group storage
 */
final class AphrontDatabaseTransactionState {

  private $depth;

  public function getDepth() {
    return $this->depth;
  }

  public function increaseDepth() {
    return ++$this->depth;
  }

  public function decreaseDepth() {
    if ($this->depth == 0) {
      throw new Exception(
        'Too many calls to saveTransaction() or killTransaction()!');
    }

    return --$this->depth;
  }

  public function getSavepointName() {
    return 'Aphront_Savepoint_'.$this->depth;
  }

  public function __destruct() {
    if ($this->depth) {
      throw new Exception(
        'Process exited with an open transaction! The transaction will be '.
        'implicitly rolled back. Calls to openTransaction() must always be '.
        'paired with a call to saveTransaction() or killTransaction().');
    }
  }

}
