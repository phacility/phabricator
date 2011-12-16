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
 * Manage attaching, detaching and updating edges between objects (for instance,
 * relationships between Tasks and Revisions).
 */
final class PhabricatorObjectAttachmentEditor {

  private $objectType;
  private $object;
  private $user;

  public function __construct($object_type, $object) {
    $this->objectType = $object_type;
    $this->object = $object;
  }

  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
  }

  public function attachObjects($attach_type, array $phids, $two_way) {
    $object_type = $this->objectType;
    $object = $this->object;
    $object_phid = $object->getPHID();

    // sort() so that removing [X, Y] and then adding [Y, X] is correctly
    // detected as a no-op.
    sort($phids);
    $old_phids = $object->getAttachedPHIDs($attach_type);
    sort($old_phids);
    $phids = array_values($phids);
    $old_phids = array_values($old_phids);

    if ($phids === $old_phids) {
      return;
    }

    $all_phids = array_merge($phids, $old_phids);
    $attach_objs = id(new PhabricatorObjectHandleData($all_phids))
      ->loadObjects();

    // Remove PHIDs which don't actually exist, to prevent silliness.
    $phids = array_keys(array_select_keys($attach_objs, $phids));
    if ($phids) {
      $phids = array_combine($phids, $phids);
    }

    // Update the primary object.
    $this->writeOutboundEdges($object_type, $object, $attach_type, $phids);

    if (!$two_way) {
      return;
    }

    // Loop through all of the attached/detached objects and update them.
    foreach ($attach_objs as $phid => $attach_obj) {
      $attached_phids = $attach_obj->getAttachedPHIDs($object_type);
      // Figure out if we're attaching or detaching this object.
      if (isset($phids[$phid])) {
        if (in_array($object_phid, $attached_phids)) {
          // Already attached.
          continue;
        }
        $attached_phids[] = $object_phid;
      } else {
        $attached_phids = array_fill_keys($attached_phids, true);
        unset($attached_phids[$object_phid]);
        $attached_phids = array_keys($attached_phids);
      }
      $this->writeOutboundEdges(
        $attach_type,
        $attach_obj,
        $object_type,
        $attached_phids);
    }
  }

  private function writeOutboundEdges(
    $object_type,
    $object,
    $attach_type,
    array $attach_phids) {
    switch ($object_type) {
      case PhabricatorPHIDConstants::PHID_TYPE_DREV:
        $object->setAttachedPHIDs($attach_type, $attach_phids);
        $object->save();
        break;
      case PhabricatorPHIDConstants::PHID_TYPE_TASK:
        $this->applyTaskTransaction(
          $object,
          $attach_type,
          $attach_phids);
        break;
    }
  }

  private function applyTaskTransaction(
    ManiphestTask $task,
    $attach_type,
    array $new_phids) {

    if (!$this->user) {
      throw new Exception("Call setUser() before editing attachments!");
    }
    $user = $this->user;

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
