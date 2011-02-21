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

class ManiphestTransactionEditor {

  public function applyTransactions($task, array $transactions) {

    $email_cc = $task->getCCPHIDs();

    $email_to = array();
    $email_to[] = $task->getOwnerPHID();

    foreach ($transactions as $key => $transaction) {
      $type = $transaction->getTransactionType();
      $new = $transaction->getNewValue();
      $email_to[] = $transaction->getAuthorPHID();

      switch ($type) {
        case ManiphestTransactionType::TYPE_NONE:
          $old = null;
          break;
        case ManiphestTransactionType::TYPE_STATUS:
          $old = $task->getStatus();
          break;
        case ManiphestTransactionType::TYPE_OWNER:
          $old = $task->getOwnerPHID();
          break;
        case ManiphestTransactionType::TYPE_CCS:
          $old = $task->getCCPHIDs();
          break;
        case ManiphestTransactionType::TYPE_PRIORITY:
          $old = $task->getPriority();
          break;
        case ManiphestTransactionType::TYPE_ATTACH:
          $old = $task->getAttached();
          break;
        case ManiphestTransactionType::TYPE_TITLE:
          $old = $task->getTitle();
          break;
        case ManiphestTransactionType::TYPE_DESCRIPTION:
          $old = $task->getDescription();
          break;
        case ManiphestTransactionType::TYPE_PROJECTS:
          $old = $task->getProjectPHIDs();
          break;
        default:
          throw new Exception('Unknown action type.');
      }

      if (($old !== null) && ($old == $new)) {
        if (count($transactions) > 1 && !$transaction->hasComments()) {
          // If we have at least one other transaction and this one isn't
          // doing anything and doesn't have any comments, just throw it
          // away.
          unset($transactions[$key]);
          continue;
        } else {
          $transaction->setOldValue(null);
          $transaction->setNewValue(null);
          $transaction->setTransactionType(ManiphestTransactionType::TYPE_NONE);
        }
      } else {
        switch ($type) {
          case ManiphestTransactionType::TYPE_NONE:
            break;
          case ManiphestTransactionType::TYPE_STATUS:
            $task->setStatus($new);
            break;
          case ManiphestTransactionType::TYPE_OWNER:
            $task->setOwnerPHID($new);
            break;
          case ManiphestTransactionType::TYPE_CCS:
            $task->setCCPHIDs($new);
            break;
          case ManiphestTransactionType::TYPE_PRIORITY:
            $task->setPriority($new);
            break;
          case ManiphestTransactionType::TYPE_ATTACH:
            $task->setAttached($new);
            break;
          case ManiphestTransactionType::TYPE_TITLE:
            $task->setTitle($new);
            break;
          case ManiphestTransactionType::TYPE_DESCRIPTION:
            $task->setDescription($new);
            break;
          case ManiphestTransactionType::TYPE_PROJECTS:
            $task->setProjectPHIDs($new);
            break;
          default:
            throw new Exception('Unknown action type.');
        }

        $transaction->setOldValue($old);
        $transaction->setNewValue($new);
      }

    }

    $task->save();
    foreach ($transactions as $transaction) {
      $transaction->setTaskID($task->getID());
      $transaction->save();
    }

    $email_to[] = $task->getOwnerPHID();
    $email_cc = array_merge(
      $email_cc,
      $task->getCCPHIDs());

    // TODO: Do this offline via timeline
    PhabricatorSearchManiphestIndexer::indexTask($task);

    $this->sendEmail($task, $transactions, $email_to, $email_cc);
  }

  private function sendEmail($task, $transactions, $email_to, $email_cc) {
    $email_to = array_filter(array_unique($email_to));
    $email_cc = array_filter(array_unique($email_cc));

    $phids = array();
    foreach ($transactions as $transaction) {
      foreach ($transaction->extractPHIDs() as $phid) {
        $phids[$phid] = true;
      }
    }
    $phids = array_keys($phids);

    $handles = id(new PhabricatorObjectHandleData($phids))
      ->loadHandles();

    $view = new ManiphestTransactionDetailView();
    $view->setTransactionGroup($transactions);
    $view->setHandles($handles);
    list($action, $body) = $view->renderForEmail($with_date = false);

    $is_create = false;
    foreach ($transactions as $transaction) {
      $type = $transaction->getTransactionType();
      if (($type == ManiphestTransactionType::TYPE_STATUS) &&
          ($transaction->getOldValue() === null) &&
          ($transaction->getNewValue() == ManiphestTaskStatus::STATUS_OPEN)) {
        $is_create = true;
      }
    }

    $task_uri = PhabricatorEnv::getURI('/T'.$task->getID());

    if ($is_create) {
      $body .=
        "\n\n".
        "TASK DESCRIPTION\n".
        "  ".$task->getDescription();
    }

    $body .=
      "\n\n".
      "TASK DETAIL\n".
      "  ".$task_uri."\n";

    $base = substr(md5($task->getPHID()), 0, 27).' '.pack("N", time());
    $thread_index = base64_encode($base);

    $message_id = '<maniphest-task-'.$task->getPHID().'>';

    id(new PhabricatorMetaMTAMail())
      ->setSubject(
        '[Maniphest] T'.$task->getID().' '.$action.':  '.$task->getTitle())
      ->setFrom($transaction->getAuthorPHID())
      ->addTos($email_to)
      ->addCCs($email_cc)
      ->addHeader('Thread-Index', $thread_index)
      ->addHeader('Thread-Topic', 'Maniphest Task '.$task->getID())
      ->addHeader('In-Reply-To',  $message_id)
      ->addHeader('References',   $message_id)
      ->setRelatedPHID($task->getPHID())
      ->setBody($body)
      ->save();
  }
}
