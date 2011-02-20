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

class ManiphestTaskEditController extends ManiphestController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = idx($data, 'id');
  }

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    if ($this->id) {
      $task = id(new ManiphestTask())->load($this->id);
      if (!$task) {
        return new Aphront404Response();
      }
    } else {
      $task = new ManiphestTask();
      $task->setPriority(ManiphestTaskPriority::PRIORITY_TRIAGE);
      $task->setAuthorPHID($user->getPHID());
    }

    $errors = array();
    $e_title = true;

    if ($request->isFormPost()) {

      $changes = array();

      $new_title = $request->getStr('title');
      $new_desc = $request->getStr('description');

      if ($task->getID()) {
        if ($new_title != $task->getTitle()) {
          $changes[ManiphestTransactionType::TYPE_TITLE] = $new_title;
        }
        if ($new_desc != $task->getDescription()) {
          $changes[ManiphestTransactionType::TYPE_DESCRIPTION] = $new_desc;
        }
      } else {
        $task->setTitle($new_title);
        $task->setDescription($new_desc);
      }

      $owner_tokenizer = $request->getArr('assigned_to');
      $owner_phid = reset($owner_tokenizer);

      if (!strlen($new_title)) {
        $e_title = 'Required';
        $errors[] = 'Title is required.';
      }

      if (!$errors) {

        $changes[ManiphestTransactionType::TYPE_STATUS] =
          ManiphestTaskStatus::STATUS_OPEN;

        if ($request->getInt('priority') != $task->getPriority()) {
          $changes[ManiphestTransactionType::TYPE_PRIORITY] =
            $request->getInt('priority');
        }

        if ($owner_phid) {
          $changes[ManiphestTransactionType::TYPE_OWNER] = $owner_phid;
        }

        if ($request->getArr('cc')) {
          $changes[ManiphestTransactionType::TYPE_CCS] = $request->getArr('cc');
        }

        $template = new ManiphestTransaction();
        $template->setAuthorPHID($user->getPHID());
        $transactions = array();

        foreach ($changes as $type => $value) {
          $transaction = clone $template;
          $transaction->setTransactionType($type);
          $transaction->setNewValue($value);
          $transactions[] = $transaction;
        }

        $editor = new ManiphestTransactionEditor();
        $editor->applyTransactions($task, $transactions);

        return id(new AphrontRedirectResponse())
          ->setURI('/T'.$task->getID());
      }
    } else {
      if (!$task->getID()) {
        $task->setCCPHIDs(array(
          $user->getPHID(),
        ));
      }
    }

    $phids = array_merge(
      array($task->getOwnerPHID()),
      $task->getCCPHIDs());
    $phids = array_filter($phids);
    $phids = array_unique($phids);

    $handles = id(new PhabricatorObjectHandleData($phids))
      ->loadHandles($phids);

    $tvalues = mpull($handles, 'getFullName', 'getPHID');

    $error_view = null;
    if ($errors) {
      $error_view = new AphrontErrorView();
      $error_view->setErrors($errors);
      $error_view->setTitle('Form Errors');
    }

    $priority_map = ManiphestTaskPriority::getTaskPriorityMap();

    if ($task->getOwnerPHID()) {
      $assigned_value = array(
        $task->getOwnerPHID() => $handles[$task->getOwnerPHID()]->getFullName(),
      );
    } else {
      $assigned_value = array();
    }

    if ($task->getCCPHIDs()) {
      $cc_value = array_select_keys($tvalues, $task->getCCPHIDs());
    } else {
      $cc_value = array();
    }

    if ($task->getID()) {
      $cancel_uri = '/T'.$task->getID();
      $button_name = 'Save Task';
      $header_name = 'Edit Task';
    } else {
      $cancel_uri = '/maniphest/';
      $button_name = 'Create Task';
      $header_name = 'Create New Task';
    }

    $form = new AphrontFormView();
    $form
      ->setUser($user)
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setLabel('Title')
          ->setName('title')
          ->setError($e_title)
          ->setHeight(AphrontFormTextAreaControl::HEIGHT_VERY_SHORT)
          ->setValue($task->getTitle()))
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setLabel('Assigned To')
          ->setName('assigned_to')
          ->setValue($assigned_value)
          ->setDatasource('/typeahead/common/users/')
          ->setLimit(1))
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setLabel('CC')
          ->setName('cc')
          ->setValue($cc_value)
          ->setDatasource('/typeahead/common/mailable/'))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel('Priority')
          ->setName('priority')
          ->setOptions($priority_map)
          ->setValue($task->getPriority()))
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setLabel('Description')
          ->setName('description')
          ->setValue($task->getDescription()))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->addCancelButton($cancel_uri)
          ->setValue($button_name));

    $panel = new AphrontPanelView();
    $panel->setWidth(AphrontPanelView::WIDTH_FULL);
    $panel->setHeader($header_name);
    $panel->appendChild($form);

    return $this->buildStandardPageResponse(
      array(
        $error_view,
        $panel,
      ),
      array(
        'title' => 'Create Task',
      ));
  }
}
