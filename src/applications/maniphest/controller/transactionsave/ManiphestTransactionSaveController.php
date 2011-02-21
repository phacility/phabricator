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
      case ManiphestTransactionType::TYPE_PROJECTS:
        $projects = $request->getArr('projects');
        $projects = array_merge($projects, $task->getProjectPHIDs());
        $projects = array_filter($projects);
        $projects = array_unique($projects);
        $transaction->setNewValue($projects);
        break;
      case ManiphestTransactionType::TYPE_CCS:
        $ccs = $request->getArr('ccs');
        $ccs = array_merge($ccs, $task->getCCPHIDs());
        $ccs = array_filter($ccs);
        $ccs = array_unique($ccs);
        $transaction->setNewValue($ccs);
        break;
      case ManiphestTransactionType::TYPE_PRIORITY:
        $transaction->setNewValue($request->getInt('priority'));
        break;
      case ManiphestTransactionType::TYPE_ATTACH:
        // This means "attach a file" even though we store other types of data
        // as 'attached'.
        $phid = null;
        if (!empty($_FILES['file'])) {
          $err = idx($_FILES['file'], 'error');
          if ($err != UPLOAD_ERR_NO_FILE) {
            $file = PhabricatorFile::newFromPHPUpload($_FILES['file']);
            $phid = $file->getPHID();
          }
        }
        if ($phid) {
          $new = $task->getAttached();
          if (empty($new['FILE'])) {
            $new['FILE'] = array();
          }
          $new['FILE'][$phid] = array();
        }

        var_dump($new);
        die();

        $transaction->setNewValue($new);
        break;
      default:
        throw new Exception('unknown action');
    }

    $transactions = array($transaction);

    switch ($action) {
      case ManiphestTransactionType::TYPE_OWNER:
        if ($task->getOwnerPHID() == $transaction->getNewValue()) {
          // If this is actually no-op, don't generate the side effect.
          break;
        }
        // When a task is reassigned, move the previous owner to CC.
        $old = $task->getCCPHIDs();
        $new = array_merge(
          $old,
          array($task->getOwnerPHID()));
        $new = array_unique(array_filter($new));
        if ($old != $new) {
          $cc = new ManiphestTransaction();
          $cc->setAuthorPHID($user->getPHID());
          $cc->setTransactionType(ManiphestTransactionType::TYPE_CCS);
          $cc->setNewValue($new);
          $transactions[] = $cc;
        }
        break;
    }

    $editor = new ManiphestTransactionEditor();
    $editor->applyTransactions($task, $transactions);

    return id(new AphrontRedirectResponse())
      ->setURI('/T'.$task->getID());
  }

}
