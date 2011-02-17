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

class DifferentialAttachController extends DifferentialController {

  private $id;
  private $type;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
    $this->type = $data['type'];
  }

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    $revision = id(new DifferentialRevision())->load($this->id);
    if (!$revision) {
      return new Aphront404Response();
    }

    if ($request->isFormPost()) {
      $phids = explode(';', $request->getStr('phids'));
      $old_phids = $revision->getAttachedPHIDs('TASK');

      if (($phids || $old_phids) && ($phids != $old_phids)) {
        $tasks = id(new ManiphestTask())->loadAllWhere(
          'phid in (%Ls)',
          array_merge($phids, $old_phids));
        $tasks = mpull($tasks, null, 'getPHID');

        // Remove PHIDs which don't actually exist.
        $phids = array_keys(array_select_keys($tasks, $phids));

        $revision->setAttachedPHIDs($this->type, $phids);
        $revision->save();

        $editor = new ManiphestTransactionEditor();
        $type = ManiphestTransactionType::TYPE_ATTACH;
        foreach ($tasks as $task) {
          $transaction = new ManiphestTransaction();
          $transaction->setAuthorPHID($user->getPHID());
          $transaction->setTransactionType($type);
          $new = $task->getAttached();
          if (empty($new['DREV'])) {
            $new['DREV'] = array();
          }
          $rev_phid = $revision->getPHID();
          if (in_array($task->getPHID(), $phids)) {
            if (in_array($rev_phid, $task->getAttachedPHIDs('DREV'))) {
              // TODO: maybe the transaction editor should be responsible for
              // this?
              continue;
            }
            $new['DREV'][$rev_phid] = array();
          } else {
            if (!in_array($rev_phid, $task->getAttachedPHIDs('DREV'))) {
              continue;
            }
            unset($new['DREV'][$rev_phid]);
          }
          $transaction->setNewValue($new);
          $editor->applyTransactions($task, array($transaction));
        }
      }

      if ($request->isAjax()) {
        return id(new AphrontRedirectResponse());
      } else {
        return id(new AphrontRedirectResponse())
          ->setURI('/D'.$revision->getID());
      }
    } else {
      $phids = $revision->getAttachedPHIDs($this->type);
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
        'open'     => 'All Open Tasks',
        'all'      => 'All Tasks',
      ))
      ->setCancelURI('#')
      ->setSearchURI('/maniphest/select/search/')
      ->setNoun('Tasks');

    $dialog = $obj_dialog->buildDialog();

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

}
