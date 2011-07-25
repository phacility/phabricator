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

class PhabricatorSearchAttachController extends PhabricatorSearchController {

  private $phid;
  private $type;
  private $action;

  const ACTION_ATTACH = 'attach';
  const ACTION_MERGE  = 'merge';

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

    $object_phid = $this->phid;
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
        case self::ACTION_ATTACH:
          // Fall through to the workflow below.
          break;
        default:
          throw new Exception("Unsupported attach action.");
      }

      // sort() so that removing [X, Y] and then adding [Y, X] is correctly
      // detected as a no-op.
      sort($phids);

      $old_phids = $object->getAttachedPHIDs($attach_type);
      sort($old_phids);

      if (($phids || $old_phids) && ($phids !== $old_phids)) {

        $all_phids = array_merge($phids, $old_phids);
        $attach_objs = id(new PhabricatorObjectHandleData($all_phids))
          ->loadObjects();

        // Remove PHIDs which don't actually exist, to prevent silliness.
        $phids = array_keys(array_select_keys($attach_objs, $phids));
        if ($phids) {
          $phids = array_combine($phids, $phids);
        }

        // Update the primary object.
        switch ($object_type) {
          case PhabricatorPHIDConstants::PHID_TYPE_DREV:
            $object->setAttachedPHIDs($attach_type, $phids);
            $object->save();
            break;
          case PhabricatorPHIDConstants::PHID_TYPE_TASK:
            $this->applyTaskTransaction(
              $object,
              $attach_type,
              $phids);
            break;
        }

        // Loop through all of the attached/detached objects and update them.
        foreach ($attach_objs as $phid => $attach_obj) {
          $attached_phids = $attach_obj->getAttachedPHIDs($object_type);
          // Figure out if we're attaching or detaching this object.
          if (isset($phids[$phid])) {
            $attached_phids[] = $object_phid;
          } else {
            $attached_phids = array_fill_keys($attached_phids, true);
            unset($attached_phids[$object_phid]);
            $attached_phids = array_keys($attached_phids);
          }
          switch ($attach_type) {
            case PhabricatorPHIDConstants::PHID_TYPE_DREV:
              $attach_obj->setAttachedPHIDs($object_type, $attached_phids);
              $attach_obj->save();
              break;
            case PhabricatorPHIDConstants::PHID_TYPE_TASK:
              $this->applyTaskTransaction(
                $attach_obj,
                $object_type,
                $attached_phids);
              break;
          }
        }
      }

      return id(new AphrontReloadResponse())->setURI($handle->getURI());
    } else {
      switch ($this->action) {
        case self::ACTION_ATTACH:
          $phids = $object->getAttachedPHIDs($attach_type);
          break;
        default:
          $phids = array();
          break;
      }
    }

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
          "The current task (\"".phutil_escape_html($handle->getName())."\") ".
          "will grow stronger.";
        break;
    }

    $handles = id(new PhabricatorObjectHandleData($phids))
      ->loadHandles();

    $obj_dialog = new PhabricatorObjectSelectorDialog();
    $obj_dialog
      ->setUser($user)
      ->setHandles($handles)
      ->setFilters(array(
        'assigned' => 'Assigned to Me',
        'created'  => 'Created By Me',
        'open'     => 'All Open '.$noun,
        'all'      => 'All '.$noun,
      ))
      ->setSelectedFilter($selected)
      ->setCancelURI($handle->getURI())
      ->setSearchURI('/search/select/'.$attach_type.'/')
      ->setTitle($dialog_title)
      ->setHeader($header_text)
      ->setButtonText($button_text)
      ->setInstructions($instructions);

    $dialog = $obj_dialog->buildDialog();

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

  private function applyTaskTransaction(
    ManiphestTask $task,
    $attach_type,
    array $new_phids) {

    $user = $this->getRequest()->getUser();

    $editor = new ManiphestTransactionEditor();
    $type = ManiphestTransactionType::TYPE_ATTACH;

    $transaction = new ManiphestTransaction();
    $transaction->setAuthorPHID($user->getPHID());
    $transaction->setTransactionType($type);

    $new = $task->getAttached();
    $new[$attach_type] = array_fill_keys($new_phids, array());

    $transaction->setNewValue($new);
    $editor->applyTransactions($task, array($transaction));
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
}
