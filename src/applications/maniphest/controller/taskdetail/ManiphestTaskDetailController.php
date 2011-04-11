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

class ManiphestTaskDetailController extends ManiphestController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    $e_title = null;

    $priority_map = ManiphestTaskPriority::getTaskPriorityMap();

    $task = id(new ManiphestTask())->load($this->id);
    if (!$task) {
      return new Aphront404Response();
    }

    $transactions = id(new ManiphestTransaction())->loadAllWhere(
      'taskID = %d ORDER BY id ASC',
      $task->getID());

    $phids = array();
    foreach ($transactions as $transaction) {
      foreach ($transaction->extractPHIDs() as $phid) {
        $phids[$phid] = true;
      }
    }
    foreach ($task->getCCPHIDs() as $phid) {
      $phids[$phid] = true;
    }
    foreach ($task->getProjectPHIDs() as $phid) {
      $phids[$phid] = true;
    }
    if ($task->getOwnerPHID()) {
      $phids[$task->getOwnerPHID()] = true;
    }
    $phids[$task->getAuthorPHID()] = true;
    $phids = array_keys($phids);

    $attached = $task->getAttached();
    foreach ($attached as $type => $list) {
      foreach ($list as $phid => $info) {
        $phids[$phid] = true;
      }
    }

    $handles = id(new PhabricatorObjectHandleData($phids))
      ->loadHandles();

    $factory = new DifferentialMarkupEngineFactory();
    $engine = $factory->newDifferentialCommentMarkupEngine();

    $dict = array();
    $dict['Status'] =
      '<strong>'.
        ManiphestTaskStatus::getTaskStatusFullName($task->getStatus()).
      '</strong>';

    $dict['Assigned To'] = $task->getOwnerPHID()
      ? $handles[$task->getOwnerPHID()]->renderLink()
      : '<em>None</em>';

    $dict['Priority'] = ManiphestTaskPriority::getTaskPriorityName(
      $task->getPriority());

    $cc = $task->getCCPHIDs();
    if ($cc) {
      $cc_links = array();
      foreach ($cc as $phid) {
        $cc_links[] = $handles[$phid]->renderLink();
      }
      $dict['CC'] = implode(', ', $cc_links);
    } else {
      $dict['CC'] = '<em>None</em>';
    }

    $dict['Author'] = $handles[$task->getAuthorPHID()]->renderLink();

    $projects = $task->getProjectPHIDs();
    if ($projects) {
      $project_links = array();
      foreach ($projects as $phid) {
        $project_links[] = $handles[$phid]->renderLink();
      }
      $dict['Projects'] = implode(', ', $project_links);
    } else {
      $dict['Projects'] = '<em>None</em>';
    }

    if (idx($attached, PhabricatorPHIDConstants::PHID_TYPE_DREV)) {
      $revs = idx($attached, PhabricatorPHIDConstants::PHID_TYPE_DREV);
      $rev_links = array();
      foreach ($revs as $rev => $info) {
        $rev_links[] = $handles[$rev]->renderLink();
      }
      $rev_links = implode(', ', $rev_links);
      $dict['Revisions'] = $rev_links;
    }

    if (idx($attached, PhabricatorPHIDConstants::PHID_TYPE_FILE)) {
      $revs = idx($attached, PhabricatorPHIDConstants::PHID_TYPE_FILE);
      $rev_links = array();
      foreach ($revs as $rev => $info) {
        $rev_links[] = $handles[$rev]->renderLink();
      }
      $rev_links = implode(', ', $rev_links);
      $dict['Files'] = $rev_links;
    }


    $dict['Description'] =
      '<div class="maniphest-task-description">'.
        '<div class="phabricator-remarkup">'.
          $engine->markupText($task->getDescription()).
        '</div>'.
      '</div>';

    require_celerity_resource('mainphest-task-detail-css');

    $table = array();
    foreach ($dict as $key => $value) {
      $table[] =
        '<tr>'.
          '<th>'.phutil_escape_html($key).':</th>'.
          '<td>'.$value.'</td>'.
        '</tr>';
    }
    $table =
      '<table class="maniphest-task-properties">'.
        implode("\n", $table).
      '</table>';


    $actions = array();

    $action = new AphrontHeadsupActionView();
    $action->setName('Edit Task');
    $action->setURI('/maniphest/task/edit/'.$task->getID().'/');
    $action->setClass('action-edit');
    $actions[] = $action;

    $action = new AphrontHeadsupActionView();
    $action->setName('Edit Differential Revisions');
    $action->setClass('action-attach unavailable');
    $actions[] = $action;

    $action_list = new AphrontHeadsupActionListView();
    $action_list->setActions($actions);

    $panel =
      '<div class="maniphest-panel">'.
        $action_list->render().
        '<div class="maniphest-task-detail-core">'.
          '<h1>'.
            phutil_escape_html('T'.$task->getID().' '.$task->getTitle()).
          '</h1>'.
          $table.
        '</div>'.
      '</div>';

    $transaction_types = ManiphestTransactionType::getTransactionTypeMap();
    $resolution_types = ManiphestTaskStatus::getTaskStatusMap();

    if ($task->getStatus() == ManiphestTaskStatus::STATUS_OPEN) {
      $resolution_types = array_select_keys(
        $resolution_types,
        array(
          ManiphestTaskStatus::STATUS_CLOSED_RESOLVED,
          ManiphestTaskStatus::STATUS_CLOSED_WONTFIX,
          ManiphestTaskStatus::STATUS_CLOSED_INVALID,
          ManiphestTaskStatus::STATUS_CLOSED_SPITE,
        ));
    } else {
      $resolution_types = array(
        ManiphestTaskStatus::STATUS_OPEN => 'Reopened',
      );
      $transaction_types[ManiphestTransactionType::TYPE_STATUS] =
        'Reopen Task';
      unset($transaction_types[ManiphestTransactionType::TYPE_PRIORITY]);
      unset($transaction_types[ManiphestTransactionType::TYPE_OWNER]);
    }

    $default_claim = array(
      $user->getPHID() => $user->getUsername().' ('.$user->getRealName().')',
    );

    $comment_form = new AphrontFormView();
    $comment_form
      ->setUser($user)
      ->setAction('/maniphest/transaction/save/')
      ->setEncType('multipart/form-data')
      ->addHiddenInput('taskID', $task->getID())
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel('Action')
          ->setName('action')
          ->setOptions($transaction_types)
          ->setID('transaction-action'))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel('Resolution')
          ->setName('resolution')
          ->setControlID('resolution')
          ->setControlStyle('display: none')
          ->setOptions($resolution_types))
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setLabel('Assign To')
          ->setName('assign_to')
          ->setControlID('assign_to')
          ->setControlStyle('display: none')
          ->setID('assign-tokenizer')
          ->setDisableBehavior(true))
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setLabel('CCs')
          ->setName('ccs')
          ->setControlID('ccs')
          ->setControlStyle('display: none')
          ->setID('cc-tokenizer')
          ->setDisableBehavior(true))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel('Priority')
          ->setName('priority')
          ->setOptions($priority_map)
          ->setControlID('priority')
          ->setControlStyle('display: none')
          ->setValue($task->getPriority()))
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setLabel('Projects')
          ->setName('projects')
          ->setControlID('projects')
          ->setControlStyle('display: none')
          ->setID('projects-tokenizer')
          ->setDisableBehavior(true))
      ->appendChild(
        id(new AphrontFormFileControl())
          ->setLabel('File')
          ->setName('file')
          ->setControlID('file')
          ->setControlStyle('display: none'))
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setLabel('Comments')
          ->setName('comments')
          ->setValue(''))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue('Avast!'));

    Javelin::initBehavior('maniphest-transaction-controls', array(
      'select' => 'transaction-action',
      'controlMap' => array(
        ManiphestTransactionType::TYPE_STATUS   => 'resolution',
        ManiphestTransactionType::TYPE_OWNER    => 'assign_to',
        ManiphestTransactionType::TYPE_CCS      => 'ccs',
        ManiphestTransactionType::TYPE_PRIORITY => 'priority',
        ManiphestTransactionType::TYPE_PROJECTS => 'projects',
        ManiphestTransactionType::TYPE_ATTACH   => 'file',
      ),
      'tokenizers' => array(
        ManiphestTransactionType::TYPE_PROJECTS => array(
          'id'    => 'projects-tokenizer',
          'src'   => '/typeahead/common/projects/',
        ),
        ManiphestTransactionType::TYPE_OWNER => array(
          'id'    => 'assign-tokenizer',
          'src'   => '/typeahead/common/users/',
          'value' => $default_claim,
          'limit' => 1,
        ),
        ManiphestTransactionType::TYPE_CCS => array(
          'id'    => 'cc-tokenizer',
          'src'   => '/typeahead/common/mailable/',
        ),
      ),
    ));

    $comment_panel = new AphrontPanelView();
    $comment_panel->appendChild($comment_form);
    $comment_panel->setHeader('Leap Into Action');

    $transaction_view = new ManiphestTransactionListView();
    $transaction_view->setTransactions($transactions);
    $transaction_view->setHandles($handles);
    $transaction_view->setUser($user);
    $transaction_view->setMarkupEngine($engine);

    return $this->buildStandardPageResponse(
      array(
        $panel,
        $transaction_view,
        $comment_panel,
      ),
      array(
        'title' => 'T'.$task->getID().' '.$task->getTitle(),
      ));
  }

}
