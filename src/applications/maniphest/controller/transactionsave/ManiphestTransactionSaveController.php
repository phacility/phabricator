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

class ManiphestTransactionSaveController extends ManiphestController {

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $task = id(new ManiphestTask())->load($request->getStr('taskID'));
    if (!$task) {
      return new Aphront404Response();
    }

    $action = $request->getStr('action');

    $transaction = new ManiphestTransaction();
    $transaction
      ->setAuthorPHID($user->getPHID())
      ->setComments($request->getStr('comments'))
      ->setTransactionType($action);

    switch ($action) {
      case ManiphestTransactionType::TYPE_NONE:
        break;
      case ManiphestTransactionType::TYPE_STATUS:
        $transaction->setNewValue($request->getStr('resolution'));
        break;
      case ManiphestTransactionType::TYPE_OWNER:
        $assign_to = $request->getArr('assign_to');
        $assign_to = reset($assign_to);
        $transaction->setNewValue($assign_to);
        break;
      case ManiphestTransactionType::TYPE_CCS:
        $ccs = $request->getArr('ccs');
        $transaction->setNewValue($ccs);
        break;
      case ManiphestTransactionType::TYPE_PRIORITY:
        $transaction->setNewValue($request->getInt('priority'));
        break;
      default:
        throw new Exception('unknown action');
    }

    $editor = new ManiphestTransactionEditor();
    $editor->applyTransaction($task, $transaction);

    return id(new AphrontRedirectResponse())
      ->setURI('/T'.$task->getID());
  }

}
