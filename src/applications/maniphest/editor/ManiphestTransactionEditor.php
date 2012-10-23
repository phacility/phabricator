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
final class ManiphestTransactionEditor extends PhabricatorEditor {

  private $parentMessageID;
  private $excludePHIDs = array();
  private $auxiliaryFields = array();

  public function setAuxiliaryFields(array $fields) {
    assert_instances_of($fields, 'ManiphestAuxiliaryFieldSpecification');
    $this->auxiliaryFields = $fields;
    return $this;
  }

  public function setParentMessageID($parent_message_id) {
    $this->parentMessageID = $parent_message_id;
    return $this;
  }

  public function setExcludePHIDs(array $exclude) {
    $this->excludePHIDs = $exclude;
    return $this;
  }

  public function getExcludePHIDs() {
    return $this->excludePHIDs;
  }

  public function applyTransactions(ManiphestTask $task, array $transactions) {
    assert_instances_of($transactions, 'ManiphestTransaction');

    $email_cc = $task->getCCPHIDs();

    $email_to = array();
    $email_to[] = $task->getOwnerPHID();

    $pri_changed = $this->isCreate($transactions);

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
        case ManiphestTransactionType::TYPE_EDGE:
          $old = $transaction->getOldValue();
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
            $pri_changed = true;
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
          case ManiphestTransactionType::TYPE_EDGE:
            // Edge edits are accomplished through PhabricatorEdgeEditor, which
            // has authority.
            break;
          default:
            throw new Exception('Unknown action type.');
        }

        $transaction->setOldValue($old);
        $transaction->setNewValue($new);
      }

    }

    if ($pri_changed) {
      $subpriority = ManiphestTransactionEditor::getNextSubpriority(
        $task->getPriority(),
        null);
      $task->setSubpriority($subpriority);
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

    $mail = $this->sendEmail($task, $transactions, $email_to, $email_cc);

    $this->publishFeedStory(
      $task,
      $transactions,
      $mail->buildRecipientList());

    // TODO: Do this offline via workers
    PhabricatorSearchManiphestIndexer::indexTask($task);

  }

  protected function getSubjectPrefix() {
    return PhabricatorEnv::getEnvConfig('metamta.maniphest.subject-prefix');
  }

  private function sendEmail($task, $transactions, $email_to, $email_cc) {
    $exclude  = $this->getExcludePHIDs();
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
    $view->setAuxiliaryFields($this->auxiliaryFields);
    list($action, $main_body) = $view->renderForEmail($with_date = false);

    $is_create = $this->isCreate($transactions);

    $task_uri = PhabricatorEnv::getURI('/T'.$task->getID());

    $reply_handler = $this->buildReplyHandler($task);

    $body = new PhabricatorMetaMTAMailBody();
    $body->addRawSection($main_body);
    if ($is_create) {
      $body->addTextSection(pht('TASK DESCRIPTION'), $task->getDescription());
    }
    $body->addTextSection(pht('TASK DETAIL'), $task_uri);
    $body->addReplySection($reply_handler->getReplyHandlerInstructions());

    $thread_id = 'maniphest-task-'.$task->getPHID();
    $task_id = $task->getID();
    $title = $task->getTitle();

    $mailtags = $this->getMailTags($transactions);

    $template = id(new PhabricatorMetaMTAMail())
      ->setSubject("T{$task_id}: {$title}")
      ->setSubjectPrefix($this->getSubjectPrefix())
      ->setVarySubjectPrefix("[{$action}]")
      ->setFrom($transaction->getAuthorPHID())
      ->setParentMessageID($this->parentMessageID)
      ->addHeader('Thread-Topic', "T{$task_id}: ".$task->getOriginalTitle())
      ->setThreadID($thread_id, $is_create)
      ->setRelatedPHID($task->getPHID())
      ->setExcludeMailRecipientPHIDs($this->getExcludeMailRecipientPHIDs())
      ->setIsBulk(true)
      ->setMailTags($mailtags)
      ->setBody($body->render());

    $mails = $reply_handler->multiplexMail(
      $template,
      array_select_keys($handles, $email_to),
      array_select_keys($handles, $email_cc));

    foreach ($mails as $mail) {
      $mail->saveAndSend();
    }

    $template->addTos($email_to);
    $template->addCCs($email_cc);
    return $template;
  }

  public function buildReplyHandler(ManiphestTask $task) {
    $handler_object = PhabricatorEnv::newObjectFromConfig(
      'metamta.maniphest.reply-handler');
    $handler_object->setMailReceiver($task);

    return $handler_object;
  }

  private function publishFeedStory(
    ManiphestTask $task,
    array $transactions,
    array $mailed_phids) {
    assert_instances_of($transactions, 'ManiphestTransaction');

    $actions = array(ManiphestAction::ACTION_UPDATE);
    $comments = null;
    foreach ($transactions as $transaction) {
      if ($transaction->hasComments()) {
        $comments = $transaction->getComments();
      }
      $type = $transaction->getTransactionType();
      switch ($type) {
        case ManiphestTransactionType::TYPE_OWNER:
          $actions[] = ManiphestAction::ACTION_ASSIGN;
          break;
        case ManiphestTransactionType::TYPE_STATUS:
          if ($task->getStatus() != ManiphestTaskStatus::STATUS_OPEN) {
            $actions[] = ManiphestAction::ACTION_CLOSE;
          } else if ($this->isCreate($transactions)) {
            $actions[] = ManiphestAction::ACTION_CREATE;
          } else {
            $actions[] = ManiphestAction::ACTION_REOPEN;
          }
          break;
        default:
          $actions[] = $type;
          break;
      }
    }

    $action_type = ManiphestAction::selectStrongestAction($actions);
    $owner_phid = $task->getOwnerPHID();
    $actor_phid = head($transactions)->getAuthorPHID();
    $author_phid = $task->getAuthorPHID();

    id(new PhabricatorFeedStoryPublisher())
      ->setStoryType('PhabricatorFeedStoryManiphest')
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
      ->setPrimaryObjectPHID($task->getPHID())
      ->setSubscribedPHIDs(
        array_merge(
          array_filter(
            array(
              $author_phid,
              $owner_phid,
              $actor_phid)),
          $task->getCCPHIDs()))
      ->setMailRecipientPHIDs($mailed_phids)
      ->publish();
  }

  private function isCreate(array $transactions) {
    assert_instances_of($transactions, 'ManiphestTransaction');
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

  private function getMailTags(array $transactions) {
    assert_instances_of($transactions, 'ManiphestTransaction');

    $tags = array();
    foreach ($transactions as $xaction) {
      switch ($xaction->getTransactionType()) {
        case ManiphestTransactionType::TYPE_CCS:
          $tags[] = MetaMTANotificationType::TYPE_MANIPHEST_CC;
          break;
        case ManiphestTransactionType::TYPE_PROJECTS:
          $tags[] = MetaMTANotificationType::TYPE_MANIPHEST_PROJECTS;
          break;
        case ManiphestTransactionType::TYPE_PRIORITY:
          $tags[] = MetaMTANotificationType::TYPE_MANIPHEST_PRIORITY;
          break;
        default:
          $tags[] = MetaMTANotificationType::TYPE_MANIPHEST_OTHER;
          break;
      }

      if ($xaction->hasComments()) {
        $tags[] = MetaMTANotificationType::TYPE_MANIPHEST_COMMENT;
      }
    }

    return array_unique($tags);
  }

  public static function getNextSubpriority($pri, $sub) {

    if ($sub === null) {
      $next = id(new ManiphestTask())->loadOneWhere(
        'priority = %d ORDER BY subpriority ASC LIMIT 1',
        $pri);
      if ($next) {
        return $next->getSubpriority() - ((double)(2 << 16));
      }
    } else {
      $next = id(new ManiphestTask())->loadOneWhere(
        'priority = %d AND subpriority > %s ORDER BY subpriority ASC LIMIT 1',
        $pri,
        $sub);
      if ($next) {
        return ($sub + $next->getSubpriority()) / 2;
      }
    }

    return (double)(2 << 32);
  }


}
