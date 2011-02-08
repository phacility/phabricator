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

    $transactions = id(new ManiphestTransaction())->loadAllWhere(
      'taskID = %d',
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
    if ($task->getOwnerPHID()) {
      $phids[$task->getOwnerPHID()] = true;
    }
    $phids[$task->getAuthorPHID()] = true;
    $phids = array_keys($phids);

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
      ? '<em>None</em>'
      : $handles[$task->getOwnerPHID()]->renderLink();

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

    $dict['Description'] = $engine->markupText($task->getDescription());

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

    $panel =
      '<div class="maniphest-panel">'.
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
      ),
      'tokenizers' => array(
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
        'title' => 'Create Task',
      ));
  }
}
