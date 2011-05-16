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

  public function willProcessRequest(array $data) {
    $this->phid = $data['phid'];
    $this->type = $data['type'];
  }

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    $handles = id(new PhabricatorObjectHandleData(array($this->phid)))
      ->loadHandles();
    $handle = $handles[$this->phid];

    $object_phid = $this->phid;
    $object_type = $handle->getType();
    $attach_type = $this->type;


    // Load the object we're going to attach/detach stuff from. This is the
    // object that triggered the action, e.g. the revision you clicked
    // "Edit Maniphest Tasks" on.
    $object = null;
    switch ($object_type) {
      case PhabricatorPHIDConstants::PHID_TYPE_DREV:
        $object = id(new DifferentialRevision())->loadOneWhere(
          'phid = %s',
          $this->phid);
        break;
      case PhabricatorPHIDConstants::PHID_TYPE_TASK:
        $object = id(new ManiphestTask())->loadOneWhere(
          'phid = %s',
          $this->phid);
        break;
    }

    if (!$object) {
      return new Aphront404Response();
    }

    if ($request->isFormPost()) {
      $phids = explode(';', $request->getStr('phids'));
      $phids = array_filter($phids);
      $phids = array_values($phids);
      // sort() so that removing [X, Y] and then adding [Y, X] is correctly
      // detected as a no-op.
      sort($phids);

      $old_phids = $object->getAttachedPHIDs($attach_type);
      sort($old_phids);

      if (($phids || $old_phids) && ($phids !== $old_phids)) {

        // Load all the objects we're attaching or detaching from the main
        // object.
        switch ($attach_type) {
          case PhabricatorPHIDConstants::PHID_TYPE_DREV:
            $attach_objs = id(new DifferentialRevision())->loadAllWhere(
              'phid IN (%Ls)',
              array_merge($phids, $old_phids));
            break;
          case PhabricatorPHIDConstants::PHID_TYPE_TASK:
            $attach_objs = id(new ManiphestTask())->loadAllWhere(
              'phid IN (%Ls)',
              array_merge($phids, $old_phids));
            break;
        }

        $attach_objs = mpull($attach_objs, null, 'getPHID');

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
      $phids = $object->getAttachedPHIDs($attach_type);
    }

    switch ($attach_type) {
      case PhabricatorPHIDConstants::PHID_TYPE_DREV:
        $noun = 'Revisions';
        $selected = 'created';
        break;
      case PhabricatorPHIDConstants::PHID_TYPE_TASK:
        $noun = 'Tasks';
        $selected = 'assigned';
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
      ->setNoun($noun);

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
}
