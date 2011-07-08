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
 * @group maniphest
 */
class ManiphestTransactionSaveController extends ManiphestController {

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $task = id(new ManiphestTask())->load($request->getStr('taskID'));
    if (!$task) {
      return new Aphront404Response();
    }

    $transactions = array();

    $action = $request->getStr('action');

    // If we have drag-and-dropped files, attach them first in a separate
    // transaction. These can come in on any transaction type, which is why we
    // handle them separately.
    $files = array();

    // Look for drag-and-drop uploads first.
    $file_phids = $request->getArr('files');
    if ($file_phids) {
      $files = id(new PhabricatorFile())->loadAllWhere(
        'phid in (%Ls)',
        $file_phids);
    }

    // This means "attach a file" even though we store other types of data
    // as 'attached'.
    if ($action == ManiphestTransactionType::TYPE_ATTACH) {
      if (!empty($_FILES['file'])) {
        $err = idx($_FILES['file'], 'error');
        if ($err != UPLOAD_ERR_NO_FILE) {
          $file = PhabricatorFile::newFromPHPUpload(
            $_FILES['file'],
            array(
              'authorPHID' => $user->getPHID(),
            ));
          $files[] = $file;
        }
      }
    }

    // If we had explicit or drag-and-drop files, create a transaction
    // for those before we deal with whatever else might have happened.
    $file_transaction = null;
    if ($files) {
      $files = mpull($files, 'getPHID', 'getPHID');
      $new = $task->getAttached();
      foreach ($files as $phid) {
        if (empty($new[PhabricatorPHIDConstants::PHID_TYPE_FILE])) {
          $new[PhabricatorPHIDConstants::PHID_TYPE_FILE] = array();
        }
        $new[PhabricatorPHIDConstants::PHID_TYPE_FILE][$phid] = array();
      }
      $transaction = new ManiphestTransaction();
      $transaction
        ->setAuthorPHID($user->getPHID())
        ->setTransactionType(ManiphestTransactionType::TYPE_ATTACH);
      $transaction->setNewValue($new);
      $transactions[] = $transaction;
      $file_transaction = $transaction;
    }

    $transaction = new ManiphestTransaction();
    $transaction
      ->setAuthorPHID($user->getPHID())
      ->setComments($request->getStr('comments'))
      ->setTransactionType($action);

    switch ($action) {
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
      case ManiphestTransactionType::TYPE_NONE:
      case ManiphestTransactionType::TYPE_ATTACH:
        // If we have a file transaction, just get rid of this secondary
        // transaction and put the comments on it instead.
        if ($file_transaction) {
          $file_transaction->setComments($transaction->getComments());
          $transaction = null;
        }
        break;
      default:
        throw new Exception('unknown action');
    }

    if ($transaction) {
      $transactions[] = $transaction;
    }

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
      case ManiphestTransactionType::TYPE_STATUS:
        if (!$task->getOwnerPHID() &&
            $request->getStr('resolution') !=
            ManiphestTaskStatus::STATUS_OPEN) {
          // Closing an unassigned task. Assign the user for this task
          $assign = new ManiphestTransaction();
          $assign->setAuthorPHID($user->getPHID());
          $assign->setTransactionType(ManiphestTransactionType::TYPE_OWNER);
          $assign->setNewValue($user->getPHID());
          $transactions[] = $assign;
        }
        break;
      case ManiphestTransactionType::TYPE_NONE:
        $ccs = $task->getCCPHIDs();
        $owner = $task->getOwnerPHID();

        if ($user->getPHID() !== $owner && !in_array($user->getPHID(), $ccs)) {
          // Current user, who is commenting, is not the owner or in ccs.
          // Add him to ccs
          $ccs[] = $user->getPHID();
          $cc = new ManiphestTransaction();
          $cc->setAuthorPHID($user->getPHID());
          $cc->setTransactionType(ManiphestTransactionType::TYPE_CCS);
          $cc->setNewValue($ccs);
          $transactions[] = $cc;
        }
      default:
        break;
    }



    $editor = new ManiphestTransactionEditor();
    $editor->applyTransactions($task, $transactions);

    $draft = id(new PhabricatorDraft())->loadOneWhere(
      'authorPHID = %s AND draftKey = %s',
      $user->getPHID(),
      $task->getPHID());
    if ($draft) {
      $draft->delete();
    }

    return id(new AphrontRedirectResponse())
      ->setURI('/T'.$task->getID());
  }

}
