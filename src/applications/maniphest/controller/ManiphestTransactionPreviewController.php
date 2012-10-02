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
final class ManiphestTransactionPreviewController extends ManiphestController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    $comments = $request->getStr('comments');

    $task = id(new ManiphestTask())->load($this->id);
    if (!$task) {
      return new Aphront404Response();
    }

    id(new PhabricatorDraft())
      ->setAuthorPHID($user->getPHID())
      ->setDraftKey($task->getPHID())
      ->setDraft($comments)
      ->replaceOrDelete();

    $action = $request->getStr('action');

    $transaction = new ManiphestTransaction();
    $transaction->setAuthorPHID($user->getPHID());
    $transaction->setComments($comments);
    $transaction->setTransactionType($action);

    $value = $request->getStr('value');
    // grab phids for handles and set transaction values based on action and
    // value (empty or control-specific format) coming in from the wire
    switch ($action) {
      case ManiphestTransactionType::TYPE_PRIORITY:
        $transaction->setOldValue($task->getPriority());
        $transaction->setNewValue($value);
        break;
      case ManiphestTransactionType::TYPE_OWNER:
        if ($value) {
          $value = current(json_decode($value));
          $phids = array($value);
        } else {
          $phids = array();
        }
        $transaction->setNewValue($value);
        break;
      case ManiphestTransactionType::TYPE_CCS:
        if ($value) {
          $value = json_decode($value);
          $phids = $value;
          foreach ($task->getCCPHIDs() as $cc_phid) {
            $phids[] = $cc_phid;
            $value[] = $cc_phid;
          }
          $transaction->setNewValue($value);
        } else {
          $phids = array();
          $transaction->setNewValue(array());
        }
        $transaction->setOldValue($task->getCCPHIDs());
        break;
      case ManiphestTransactionType::TYPE_PROJECTS:
        if ($value) {
          $value = json_decode($value);
          $phids = $value;
          foreach ($task->getProjectPHIDs() as $project_phid) {
            $phids[] = $project_phid;
            $value[] = $project_phid;
          }
          $transaction->setNewValue($value);
        } else {
          $phids = array();
          $transaction->setNewValue(array());
        }
        $transaction->setOldValue($task->getProjectPHIDs());
        break;
      default:
        $phids = array();
        $transaction->setNewValue($value);
        break;
    }
    $phids[] = $user->getPHID();

    $handles = $this->loadViewerHandles($phids);

    $transactions   = array();
    $transactions[] = $transaction;

    $engine = new PhabricatorMarkupEngine();
    $engine->setViewer($user);
    $engine->addObject($transaction, ManiphestTransaction::MARKUP_FIELD_BODY);
    $engine->process();

    $transaction_view = new ManiphestTransactionListView();
    $transaction_view->setTransactions($transactions);
    $transaction_view->setHandles($handles);
    $transaction_view->setUser($user);
    $transaction_view->setMarkupEngine($engine);
    $transaction_view->setPreview(true);

    return id(new AphrontAjaxResponse())
      ->setContent($transaction_view->render());
  }

}
