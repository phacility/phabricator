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
 * @group conduit
 */
final class ConduitAPI_maniphest_createtask_Method
  extends ConduitAPI_maniphest_Method {

  public function getMethodDescription() {
    return "Create a new Maniphest task.";
  }

  public function defineParamTypes() {
    return array(
      'title'         => 'required string',
      'description'   => 'optional string',
      'ownerPHID'     => 'optional phid',
      'ccPHIDs'       => 'optional list<phid>',
      'priority'      => 'optional int',
      'projectPHIDs'  => 'optional list<phid>',
      'filePHIDs'     => 'optional list<phid>',
    );
  }

  public function defineReturnType() {
    return 'nonempty dict';
  }

  public function defineErrorTypes() {
    return array(
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $task = new ManiphestTask();
    $task->setPriority(ManiphestTaskPriority::PRIORITY_TRIAGE);
    $task->setAuthorPHID($request->getUser()->getPHID());

    $task->setTitle((string)$request->getValue('title'));
    $task->setDescription((string)$request->getValue('description'));

    $changes = array();
    $changes[ManiphestTransactionType::TYPE_STATUS] =
      ManiphestTaskStatus::STATUS_OPEN;

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
      $changes[ManiphestTransactionType::TYPE_ATTACH] = array(
        PhabricatorPHIDConstants::PHID_TYPE_FILE => $file_map,
      );
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
      $transactions[] = $transaction;
    }

    $event = new PhabricatorEvent(
      PhabricatorEventType::TYPE_MANIPHEST_WILLEDITTASK,
      array(
        'task'          => $task,
        'new'           => true,
        'transactions'  => $transactions,
      ));
    $event->setUser($request->getUser());
    $event->setConduitRequest($request);
    PhabricatorEventEngine::dispatchEvent($event);

    $task = $event->getValue('task');
    $transactions = $event->getValue('transactions');

    $editor = new ManiphestTransactionEditor();
    $editor->applyTransactions($task, $transactions);

    return $this->buildTaskInfoDictionary($task);
  }

}
