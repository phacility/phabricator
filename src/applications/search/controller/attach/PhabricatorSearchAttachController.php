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
 * @group search
 */
final class PhabricatorSearchAttachController
  extends PhabricatorSearchBaseController {

  private $phid;
  private $type;
  private $action;

  const ACTION_ATTACH       = 'attach';
  const ACTION_MERGE        = 'merge';
  const ACTION_DEPENDENCIES = 'dependencies';

  public function willProcessRequest(array $data) {
    $this->phid = $data['phid'];
    $this->type = $data['type'];
    $this->action = idx($data, 'action', self::ACTION_ATTACH);
  }

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    $handle_data = new PhabricatorObjectHandleData(array($this->phid));
    $handles = $handle_data->loadHandles();
    $handle = $handles[$this->phid];

    $object_type = $handle->getType();
    $attach_type = $this->type;

    $objects = $handle_data->loadObjects();
    $object = idx($objects, $this->phid);

    if (!$object) {
      return new Aphront404Response();
    }

    if ($request->isFormPost()) {
      $phids = explode(';', $request->getStr('phids'));
      $phids = array_filter($phids);
      $phids = array_values($phids);

      switch ($this->action) {
        case self::ACTION_MERGE:
          return $this->performMerge($object, $handle, $phids);

        case self::ACTION_DEPENDENCIES:
        case self::ACTION_ATTACH:
          $two_way = true;
          if ($this->action == self::ACTION_DEPENDENCIES) {
            $two_way = false;
            $this->detectGraphCycles(
              $object,
              $attach_type,
              $phids);
          }

          $editor = new PhabricatorObjectAttachmentEditor(
            $object_type,
            $object);
          $editor->setUser($this->getRequest()->getUser());
          $editor->attachObjects(
            $attach_type,
            $phids,
            $two_way);

          return id(new AphrontReloadResponse())->setURI($handle->getURI());
        default:
          throw new Exception("Unsupported attach action.");
      }
    } else {
      switch ($this->action) {
        case self::ACTION_ATTACH:
        case self::ACTION_DEPENDENCIES:
          $phids = $object->getAttachedPHIDs($attach_type);
          break;
        default:
          $phids = array();
          break;
      }
    }

    $strings = $this->getStrings();

    $handles = id(new PhabricatorObjectHandleData($phids))
      ->loadHandles();

    $obj_dialog = new PhabricatorObjectSelectorDialog();
    $obj_dialog
      ->setUser($user)
      ->setHandles($handles)
      ->setFilters(array(
        'assigned' => 'Assigned to Me',
        'created'  => 'Created By Me',
        'open'     => 'All Open '.$strings['target_plural_noun'],
        'all'      => 'All '.$strings['target_plural_noun'],
      ))
      ->setSelectedFilter($strings['selected'])
      ->setCancelURI($handle->getURI())
      ->setSearchURI('/search/select/'.$attach_type.'/')
      ->setTitle($strings['title'])
      ->setHeader($strings['header'])
      ->setButtonText($strings['button'])
      ->setInstructions($strings['instructions']);

    $dialog = $obj_dialog->buildDialog();

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

  private function performMerge(
    ManiphestTask $task,
    PhabricatorObjectHandle $handle,
    array $phids) {

    $user = $this->getRequest()->getUser();
    $response = id(new AphrontReloadResponse())->setURI($handle->getURI());

    $phids = array_fill_keys($phids, true);
    unset($phids[$task->getPHID()]); // Prevent merging a task into itself.

    if (!$phids) {
      return $response;
    }

    $targets = id(new ManiphestTask())->loadAllWhere(
      'phid in (%Ls) ORDER BY id ASC',
      array_keys($phids));

    if (empty($targets)) {
      return $response;
    }

    $editor = new ManiphestTransactionEditor();

    $task_names = array();

    $merge_into_name = 'T'.$task->getID();

    $cc_vector = array();
    $cc_vector[] = $task->getCCPHIDs();
    foreach ($targets as $target) {
      $cc_vector[] = $target->getCCPHIDs();
      $cc_vector[] = array(
        $target->getAuthorPHID(),
        $target->getOwnerPHID());

      $close_task = id(new ManiphestTransaction())
        ->setAuthorPHID($user->getPHID())
        ->setTransactionType(ManiphestTransactionType::TYPE_STATUS)
        ->setNewValue(ManiphestTaskStatus::STATUS_CLOSED_DUPLICATE)
        ->setComments("\xE2\x9C\x98 Merged into {$merge_into_name}.");

      $editor->applyTransactions($target, array($close_task));

      $task_names[] = 'T'.$target->getID();
    }
    $all_ccs = array_mergev($cc_vector);
    $all_ccs = array_filter($all_ccs);
    $all_ccs = array_unique($all_ccs);

    $task_names = implode(', ', $task_names);

    $add_ccs = id(new ManiphestTransaction())
      ->setAuthorPHID($user->getPHID())
      ->setTransactionType(ManiphestTransactionType::TYPE_CCS)
      ->setNewValue($all_ccs)
      ->setComments("\xE2\x97\x80 Merged tasks: {$task_names}.");
    $editor->applyTransactions($task, array($add_ccs));

    return $response;
  }

  private function getStrings() {
    switch ($this->type) {
      case PhabricatorPHIDConstants::PHID_TYPE_DREV:
        $noun = 'Revisions';
        $selected = 'created';
        break;
      case PhabricatorPHIDConstants::PHID_TYPE_TASK:
        $noun = 'Tasks';
        $selected = 'assigned';
        break;
    }

    switch ($this->action) {
      case self::ACTION_ATTACH:
        $dialog_title = "Manage Attached {$noun}";
        $header_text = "Currently Attached {$noun}";
        $button_text = "Save {$noun}";
        $instructions = null;
        break;
      case self::ACTION_MERGE:
        $dialog_title = "Merge Duplicate Tasks";
        $header_text = "Tasks To Merge";
        $button_text = "Merge {$noun}";
        $instructions =
          "These tasks will be merged into the current task and then closed. ".
          "The current task will grow stronger.";
        break;
      case self::ACTION_DEPENDENCIES:
        $dialog_title = "Edit Dependencies";
        $header_text = "Current Dependencies";
        $button_text = "Save Dependencies";
        $instructions = null;
        break;
    }

    return array(
      'target_plural_noun'    => $noun,
      'selected'              => $selected,
      'title'                 => $dialog_title,
      'header'                => $header_text,
      'button'                => $button_text,
      'instructions'          => $instructions,
    );
  }

  private function detectGraphCycles(
    $object,
    $attach_type,
    array $phids) {

    // Detect graph cycles.
    $graph = new PhabricatorObjectGraph();
    $graph->setEdgeType($attach_type);
    $graph->addNodes(array(
      $object->getPHID() => $phids,
    ));
    $graph->loadGraph();

    foreach ($phids as $phid) {
      $cycle = $graph->detectCycles($phid);
      if (!$cycle) {
        continue;
      }

      // TODO: Improve this behavior so it's not all-or-nothing?

      $handles = id(new PhabricatorObjectHandleData($cycle))
        ->loadHandles();
      $names = array();
      foreach ($cycle as $cycle_phid) {
        $names[] = $handles[$cycle_phid]->getFullName();
      }
      $names = implode(" \xE2\x86\x92 ", $names);
      $which = $handles[$phid]->getFullName();
      throw new Exception(
        "You can not create a dependency on '{$which}' because it ".
        "would create a circular dependency: {$names}.");
    }
  }

}
