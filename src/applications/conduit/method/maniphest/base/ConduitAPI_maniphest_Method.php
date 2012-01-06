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
 * @group conduit
 */
abstract class ConduitAPI_maniphest_Method extends ConduitAPIMethod {

  protected function buildTaskInfoDictionary(ManiphestTask $task) {
    $results = $this->buildTaskInfoDictionaries(array($task));
    return idx($results, $task->getPHID());
  }

  protected function getTaskFields($is_new) {
    $fields = array();

    if (!$is_new) {
      $fields += array(
        'id'        => 'optional int',
        'phid'      => 'optional int',
      );
    }

    $fields += array(
      'title'         => $is_new ? 'required string' : 'optional string',
      'description'   => 'optional string',
      'ownerPHID'     => 'optional phid',
      'ccPHIDs'       => 'optional list<phid>',
      'priority'      => 'optional int',
      'projectPHIDs'  => 'optional list<phid>',
      'filePHIDs'     => 'optional list<phid>',
      'auxiliary'     => 'optional dict',
    );

    if (!$is_new) {
      $fields += array(
        'status'    => 'optional int',
        'comments'  => 'optional string',
      );
    }

    return $fields;
  }

  protected function applyRequest(
    ManiphestTask $task,
    ConduitAPIRequest $request,
    $is_new) {

    $changes = array();

    if ($is_new) {
      $task->setTitle((string)$request->getValue('title'));
      $task->setDescription((string)$request->getValue('description'));
      $changes[ManiphestTransactionType::TYPE_STATUS] =
        ManiphestTaskStatus::STATUS_OPEN;
    } else {

      $comments = $request->getValue('comments');
      if (!$is_new && $comments !== null) {
        $changes[ManiphestTransactionType::TYPE_NONE] = null;
      }

      $title = $request->getValue('title');
      if ($title !== null) {
        $changes[ManiphestTransactionType::TYPE_TITLE] = $title;
      }

      $desc = $request->getValue('description');
      if ($desc !== null) {
        $changes[ManiphestTransactionType::TYPE_DESCRIPTION] = $desc;
      }

      $status = $request->getValue('status');
      if ($status !== null) {
        $changes[ManiphestTransactionType::TYPE_STATUS] = $status;
      }
    }

    $priority = $request->getValue('priority');
    if ($priority !== null) {
      $changes[ManiphestTransactionType::TYPE_PRIORITY] = $priority;
    }

    $owner_phid = $request->getValue('ownerPHID');
    if ($owner_phid !== null) {
      $changes[ManiphestTransactionType::TYPE_OWNER] = $owner_phid;
    }

    $ccs = $request->getValue('ccPHIDs');
    if ($ccs !== null) {
      $changes[ManiphestTransactionType::TYPE_CCS] = $ccs;
    }

    $project_phids = $request->getValue('projectPHIDs');
    if ($project_phids !== null) {
      $changes[ManiphestTransactionType::TYPE_PROJECTS] = $project_phids;
    }

    $file_phids = $request->getValue('filePHIDs');
    if ($file_phids !== null) {
      $file_map = array_fill_keys($file_phids, true);
      $attached = $task->getAttached();
      $attached[PhabricatorPHIDConstants::PHID_TYPE_FILE] = $file_map;

      $changes[ManiphestTransactionType::TYPE_ATTACH] = $attached;
    }

    $content_source = PhabricatorContentSource::newForSource(
      PhabricatorContentSource::SOURCE_CONDUIT,
      array());

    $template = new ManiphestTransaction();
    $template->setContentSource($content_source);
    $template->setAuthorPHID($request->getUser()->getPHID());

    $transactions = array();
    foreach ($changes as $type => $value) {
      $transaction = clone $template;
      $transaction->setTransactionType($type);
      $transaction->setNewValue($value);
      if ($type == ManiphestTransactionType::TYPE_NONE) {
        $transaction->setComments($comments);
      }
      $transactions[] = $transaction;
    }

    $auxiliary = $request->getValue('auxiliary');
    if ($auxiliary) {
      $task->loadAndAttachAuxiliaryAttributes();
      foreach ($auxiliary as $aux_key => $aux_value) {
        $transaction = clone $template;
        $transaction->setTransactionType(
          ManiphestTransactionType::TYPE_AUXILIARY);
        $transaction->setMetadataValue('aux:key', $aux_key);
        $transaction->setNewValue($aux_value);
        $transactions[] = $transaction;
      }
    }

    $event = new PhabricatorEvent(
      PhabricatorEventType::TYPE_MANIPHEST_WILLEDITTASK,
      array(
        'task'          => $task,
        'new'           => $is_new,
        'transactions'  => $transactions,
      ));
    $event->setUser($request->getUser());
    $event->setConduitRequest($request);
    PhutilEventEngine::dispatchEvent($event);

    $task = $event->getValue('task');
    $transactions = $event->getValue('transactions');

    $editor = new ManiphestTransactionEditor();
    $editor->applyTransactions($task, $transactions);
  }

  protected function buildTaskInfoDictionaries(array $tasks) {
    if (!$tasks) {
      return array();
    }

    $all_aux = id(new ManiphestTaskAuxiliaryStorage())->loadAllWhere(
      'taskPHID in (%Ls)',
      mpull($tasks, 'getPHID'));
    $all_aux = mgroup($all_aux, 'getTaskPHID');

    $result = array();
    foreach ($tasks as $task) {
      $auxiliary = idx($all_aux, $task->getPHID(), array());
      $auxiliary = mpull($auxiliary, 'getValue', 'getName');

      $result[$task->getPHID()] = array(
        'id'           => $task->getID(),
        'phid'         => $task->getPHID(),
        'authorPHID'   => $task->getAuthorPHID(),
        'ownerPHID'    => $task->getOwnerPHID(),
        'ccPHIDs'      => $task->getCCPHIDs(),
        'status'       => $task->getStatus(),
        'priority'     => ManiphestTaskPriority::getTaskPriorityName(
          $task->getPriority()),
        'title'        => $task->getTitle(),
        'description'  => $task->getDescription(),
        'projectPHIDs' => $task->getProjectPHIDs(),
        'uri'          => PhabricatorEnv::getProductionURI('/T'.$task->getID()),
        'auxiliary'    => $auxiliary,

        'objectName'   => 'T'.$task->getID(),
        'dateCreated'  => $task->getDateCreated(),
        'dateModified' => $task->getDateModified(),
      );
    }

    return $result;
  }

}
