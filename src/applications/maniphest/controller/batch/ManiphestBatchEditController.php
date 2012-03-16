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
final class ManiphestBatchEditController extends ManiphestController {

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    $task_ids = $request->getArr('batch');
    $tasks = id(new ManiphestTask())->loadAllWhere(
      'id IN (%Ld)',
      $task_ids);

    $actions = $request->getStr('actions');
    if ($actions) {
      $actions = json_decode($actions, true);
    }

    if ($request->isFormPost() && is_array($actions)) {
      foreach ($tasks as $task) {
        $xactions = $this->buildTransactions($actions, $task);
        if ($xactions) {
          $editor = new ManiphestTransactionEditor();
          $editor->applyTransactions($task, $xactions);
        }
      }

      return id(new AphrontRedirectResponse())
        ->setURI('/maniphest/');
    }

    $panel = new AphrontPanelView();
    $panel->setHeader('Maniphest Batch Editor');

    $handle_phids = mpull($tasks, 'getOwnerPHID');
    $handles = id(new PhabricatorObjectHandleData($handle_phids))
      ->loadHandles();

    $list = new ManiphestTaskListView();
    $list->setTasks($tasks);
    $list->setUser($user);
    $list->setHandles($handles);

    $template = new AphrontTokenizerTemplateView();
    $template = $template->render();

    require_celerity_resource('maniphest-batch-editor');
    Javelin::initBehavior(
      'maniphest-batch-editor',
      array(
        'root' => 'maniphest-batch-edit-form',
        'tokenizerTemplate' => $template,
        'sources' => array(
          'project' => '/typeahead/common/projects/',
        ),
        'input' => 'batch-form-actions',
      ));

    $form = new AphrontFormView();
    $form->setUser($user);
    $form->setID('maniphest-batch-edit-form');

    foreach ($tasks as $task) {
      $form->appendChild(
        phutil_render_tag(
          'input',
          array(
            'type' => 'hidden',
            'name' => 'batch[]',
            'value' => $task->getID(),
          ),
          null));
    }

    $form->appendChild(
      phutil_render_tag(
        'input',
        array(
          'type' => 'hidden',
          'name' => 'actions',
          'id'   => 'batch-form-actions',
        ),
        null));
    $form->appendChild('<p>These tasks will be edited:</p>');
    $form->appendChild($list);
    $form->appendChild(
      id(new AphrontFormInsetView())
        ->setTitle('Actions')
        ->setRightButton(javelin_render_tag(
            'a',
            array(
              'href' => '#',
              'class' => 'button green',
              'sigil' => 'add-action',
              'mustcapture' => true,
            ),
            'Add Another Action'))
        ->setContent(javelin_render_tag(
          'table',
          array(
            'sigil' => 'maniphest-batch-actions',
            'class' => 'maniphest-batch-actions-table',
          ),
          '')))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue('Update Tasks')
          ->addCancelButton('/maniphest/', 'Done'));

    $panel->appendChild($form);


    return $this->buildStandardPageResponse(
      $panel,
      array(
        'title' => 'Batch Editor',
      ));
  }

  private function buildTransactions($actions, ManiphestTask $task) {
    $template = new ManiphestTransaction();
    $template->setAuthorPHID($this->getRequest()->getUser()->getPHID());

    // TODO: Set content source to "batch edit".

    $xactions = array();
    foreach ($actions as $action) {
      $value = $action['value'];
      switch ($action['action']) {
        case 'add_project':
        case 'remove_project':

          $is_remove = ($action['action'] == 'remove_project');

          $current = array_fill_keys($task->getProjectPHIDs(), true);
          $value   = array_fill_keys($value, true);

          $new = $current;
          $did_something = false;

          if ($is_remove) {
            foreach ($value as $phid => $ignored) {
              if (isset($new[$phid])) {
                unset($new[$phid]);
                $did_something = true;
              }
            }
          } else {
            foreach ($value as $phid => $ignored) {
              if (empty($new[$phid])) {
                $new[$phid] = true;
                $did_something = true;
              }
            }
          }

          if (!$did_something) {
            break;
          }

          $new = array_keys($new);
          $xaction = clone $template;
          $xaction->setTransactionType(ManiphestTransactionType::TYPE_PROJECTS);
          $xaction->setNewValue($new);
          $xactions[] = $xaction;
          break;
      }
    }

    return $xactions;
  }

}
