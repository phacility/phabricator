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
class ManiphestTransactionEditor {

  private $parentMessageID;

  public function setParentMessageID($parent_message_id) {
    $this->parentMessageID = $parent_message_id;
    return $this;
  }

  public function applyTransactions($task, array $transactions) {

    $email_cc = $task->getCCPHIDs();

    $email_to = array();
    $email_to[] = $task->getOwnerPHID();

    foreach ($transactions as $key => $transaction) {
      $type = $transaction->getTransactionType();
      $new = $transaction->getNewValue();
      $email_to[] = $transaction->getAuthorPHID();

      $value_is_phid_set = false;

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
          $value_is_phid_set = true;
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
          $value_is_phid_set = true;
          break;
        case ManiphestTransactionType::TYPE_AUXILIARY:
          $aux_key = $transaction->getMetadataValue('aux:key');
          if (!$aux_key) {
            throw new Exception(
              "Expected 'aux:key' metadata on TYPE_AUXILIARY transaction.");
          }
          $old = $task->getAuxiliaryAttribute($aux_key);
          break;
        default:
          throw new Exception('Unknown action type.');
      }

      $old_cmp = $old;
      $new_cmp = $new;
      if ($value_is_phid_set) {

        // Normalize the old and new values if they are PHID sets so we don't
        // get any no-op transactions where the values differ only by keys,
        // order, duplicates, etc.

        if (is_array($old)) {
          $old = array_filter($old);
          $old = array_unique($old);
          sort($old);
          $old = array_values($old);
          $old_cmp = $old;
        }

        if (is_array($new)) {
          $new = array_filter($new);
          $new = array_unique($new);
          $transaction->setNewValue($new);

          $new_cmp = $new;
          sort($new_cmp);
          $new_cmp = array_values($new_cmp);
        }
      }

      if (($old !== null) && ($old_cmp == $new_cmp)) {
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
            if ($new) {
              $handles = id(new PhabricatorObjectHandleData(array($new)))
                ->loadHandles();
              $task->setOwnerOrdering($handles[$new]->getName());
            } else {
              $task->setOwnerOrdering(null);
            }
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
          case ManiphestTransactionType::TYPE_AUXILIARY:
            $aux_key = $transaction->getMetadataValue('aux:key');
            $task->setAuxiliaryAttribute($aux_key, $new);
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

    $this->publishFeedStory($task, $transactions);

    // TODO: Do this offline via timeline
    PhabricatorSearchManiphestIndexer::indexTask($task);

    $this->sendEmail($task, $transactions, $email_to, $email_cc);
  }

  protected function getSubjectPrefix() {
    return PhabricatorEnv::getEnvConfig('metamta.maniphest.subject-prefix');
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
    foreach ($email_to as $phid) {
      $phids[$phid] = true;
    }
    foreach ($email_cc as $phid) {
      $phids[$phid] = true;
    }
    $phids = array_keys($phids);

    $handles = id(new PhabricatorObjectHandleData($phids))
      ->loadHandles();

    $view = new ManiphestTransactionDetailView();
    $view->setTransactionGroup($transactions);
    $view->setHandles($handles);
    list($action, $body) = $view->renderForEmail($with_date = false);

    $is_create = $this->isCreate($transactions);

    $task_uri = PhabricatorEnv::getURI('/T'.$task->getID());

    $reply_handler = $this->buildReplyHandler($task);

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

    $reply_instructions = $reply_handler->getReplyHandlerInstructions();
    if ($reply_instructions) {
      $body .=
        "\n".
        "REPLY HANDLER ACTIONS\n".
        "  ".$reply_instructions."\n";
    }

    $thread_id = '<maniphest-task-'.$task->getPHID().'>';
    $task_id = $task->getID();
    $title = $task->getTitle();
    $prefix = $this->getSubjectPrefix();
    $subject = trim("{$prefix} [{$action}] T{$task_id}: {$title}");

    $template = id(new PhabricatorMetaMTAMail())
      ->setSubject($subject)
      ->setFrom($transaction->getAuthorPHID())
      ->setParentMessageID($this->parentMessageID)
      ->addHeader('Thread-Topic', 'Maniphest Task '.$task->getID())
      ->setThreadID($thread_id, $is_create)
      ->setRelatedPHID($task->getPHID())
      ->setIsBulk(true)
      ->setBody($body);

    $mails = $reply_handler->multiplexMail(
      $template,
      array_select_keys($handles, $email_to),
      array_select_keys($handles, $email_cc));

    foreach ($mails as $mail) {
      $mail->saveAndSend();
    }
  }

  public function buildReplyHandler(ManiphestTask $task) {
    $handler_class = PhabricatorEnv::getEnvConfig(
      'metamta.maniphest.reply-handler');

    $handler_object = newv($handler_class, array());
    $handler_object->setMailReceiver($task);

    return $handler_object;
  }

  private function publishFeedStory(ManiphestTask $task, array $transactions) {
    $actions = array(ManiphestAction::ACTION_UPDATE);
    $comments = null;
    foreach ($transactions as $transaction) {
      if ($transaction->hasComments()) {
        $comments = $transaction->getComments();
      }
      switch ($transaction->getTransactionType()) {
        case ManiphestTransactionType::TYPE_OWNER:
          $actions[] = ManiphestAction::ACTION_ASSIGN;
          break;
        case ManiphestTransactionType::TYPE_STATUS:
          if ($task->getStatus() != ManiphestTaskStatus::STATUS_OPEN) {
            $actions[] = ManiphestAction::ACTION_CLOSE;
          } else if ($this->isCreate($transactions)) {
            $actions[] = ManiphestAction::ACTION_CREATE;
          }
          break;
        default:
          break;
      }
    }

    $action_type = ManiphestAction::selectStrongestAction($actions);
    $owner_phid = $task->getOwnerPHID();
    $actor_phid = head($transactions)->getAuthorPHID();
    $author_phid = $task->getAuthorPHID();

    id(new PhabricatorFeedStoryPublisher())
      ->setStoryType(PhabricatorFeedStoryTypeConstants::STORY_MANIPHEST)
      ->setStoryData(array(
        'taskPHID'        => $task->getPHID(),
        'transactionIDs'  => mpull($transactions, 'getID'),
        'ownerPHID'       => $owner_phid,
        'action'          => $action_type,
        'comments'        => $comments,
        'description'     => $task->getDescription(),
      ))
      ->setStoryTime(time())
      ->setStoryAuthorPHID($actor_phid)
      ->setRelatedPHIDs(
        array_merge(
          array_filter(
            array(
              $task->getPHID(),
              $author_phid,
              $actor_phid,
              $owner_phid,
            )),
          $task->getProjectPHIDs()))
      ->publish();
  }

  private function isCreate(array $transactions) {
    $is_create = false;
    foreach ($transactions as $transaction) {
      $type = $transaction->getTransactionType();
      if (($type == ManiphestTransactionType::TYPE_STATUS) &&
          ($transaction->getOldValue() === null) &&
          ($transaction->getNewValue() == ManiphestTaskStatus::STATUS_OPEN)) {
        $is_create = true;
      }
    }
    return $is_create;
  }

}
