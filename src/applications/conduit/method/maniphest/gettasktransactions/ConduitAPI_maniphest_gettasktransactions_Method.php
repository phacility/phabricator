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
 * @group maniphest
 */
final class ConduitAPI_maniphest_gettasktransactions_Method
  extends ConduitAPI_maniphest_Method {

  public function getMethodDescription() {
    return "Retrieve Maniphest Task Transactions.";
  }

  public function defineParamTypes() {
    return array(
      'ids' => 'required list<int>',
    );
  }

  public function defineReturnType() {
    return 'nonempty list<dict<string, wild>>';
  }

  public function defineErrorTypes() {
    return array(
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $results = array();
    $task_ids = $request->getValue('ids');

    if (!$task_ids) {
      return $results;
    }

    $transactions = id(new ManiphestTransaction())->loadAllWhere(
      'taskID IN (%Ld) ORDER BY id ASC',
      $task_ids);

    foreach ($transactions as $transaction) {
      $task_id = $transaction->getTaskID();
      if (!array_key_exists($task_id, $results)) {
        $results[$task_id] = array();
      }
      $results[$task_id][] = array(
        'taskID'  => $task_id,
        'transactionType'  => $transaction->getTransactionType(),
        'oldValue'  => $transaction->getOldValue(),
        'newValue'  => $transaction->getNewValue(),
        'comments'      => $transaction->getComments(),
        'authorPHID'  => $transaction->getAuthorPHID(),
        'dateCreated' => $transaction->getDateCreated(),
      );
    }

    return $results;
  }
}
