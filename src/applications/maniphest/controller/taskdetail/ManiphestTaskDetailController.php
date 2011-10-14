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
 * @group maniphest
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

    $workflow = $request->getStr('workflow');
    $parent_task = null;
    if ($workflow && is_numeric($workflow)) {
      $parent_task = id(new ManiphestTask())->load($workflow);
    }

    $extensions = ManiphestTaskExtensions::newExtensions();
    $aux_fields = $extensions->getAuxiliaryFieldSpecifications();

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

    $attached = $task->getAttached();
    foreach ($attached as $type => $list) {
      foreach ($list as $phid => $info) {
        $phids[$phid] = true;
      }
    }

    if ($parent_task) {
      $phids[$parent_task->getPHID()] = true;
    }

    $phids = array_keys($phids);

    $handles = id(new PhabricatorObjectHandleData($phids))
      ->loadHandles();

    $engine = PhabricatorMarkupEngine::newManiphestMarkupEngine();

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

    $source = $task->getOriginalEmailSource();
    if ($source) {
      $subject = '[T'.$task->getID().'] '.$task->getTitle();
      $dict['From Email'] = phutil_render_tag(
        'a',
        array(
          'href' => 'mailto:'.$source.'?subject='.$subject
        ),
        phutil_escape_html($source));
    }

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

    if ($aux_fields) {
      foreach ($aux_fields as $aux_field) {
        $attribute = $task->loadAuxiliaryAttribute(
          $aux_field->getAuxiliaryKey()
        );

        if ($attribute) {
          $aux_field->setValue($attribute->getValue());
        }

        $dict[$aux_field->getLabel()] = $aux_field->renderForDetailView();
      }
    }

    $dtasks = idx($attached, PhabricatorPHIDConstants::PHID_TYPE_TASK);
    if ($dtasks) {
      $dtask_links = array();
      foreach ($dtasks as $dtask => $info) {
        $dtask_links[] = $handles[$dtask]->renderLink();
      }
      $dtask_links = implode('<br />', $dtask_links);
      $dict['Depends On'] = $dtask_links;
    }

    $revs = idx($attached, PhabricatorPHIDConstants::PHID_TYPE_DREV);
    if ($revs) {
      $rev_links = array();
      foreach ($revs as $rev => $info) {
        $rev_links[] = $handles[$rev]->renderLink();
      }
      $rev_links = implode('<br />', $rev_links);
      $dict['Revisions'] = $rev_links;
    }

    $file_infos = idx($attached, PhabricatorPHIDConstants::PHID_TYPE_FILE);
    if ($file_infos) {
      $file_phids = array_keys($file_infos);

      $files = id(new PhabricatorFile())->loadAllWhere(
        'phid IN (%Ls)',
        $file_phids);

      $views = array();
      foreach ($files as $file) {
        $view = new AphrontFilePreviewView();
        $view->setFile($file);
        $views[] = $view->render();
      }
      $dict['Files'] = implode('', $views);
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

    $context_bar = null;

    if ($parent_task) {
      $context_bar = new AphrontContextBarView();
      $context_bar->addButton(
         phutil_render_tag(
         'a',
         array(
           'href' => '/maniphest/task/create/?parent='.$parent_task->getID(),
           'class' => 'green button',
         ),
        'Create Another Subtask'));
      $context_bar->appendChild(
        'Created a subtask of <strong>'.
        $handles[$parent_task->getPHID()]->renderLink().
        '</strong>');
    } else if ($workflow == 'create') {
      $context_bar = new AphrontContextBarView();
      $context_bar->addButton(
         phutil_render_tag(
         'a',
         array(
           'href' => '/maniphest/task/create/?template='.$task->getID(),
           'class' => 'green button',
         ),
        'Create Another Task'));
      $context_bar->appendChild('New task created.');
    }

    $actions = array();

    $action = new AphrontHeadsupActionView();
    $action->setName('Edit Task');
    $action->setURI('/maniphest/task/edit/'.$task->getID().'/');
    $action->setClass('action-edit');
    $actions[] = $action;

    require_celerity_resource('phabricator-object-selector-css');
    require_celerity_resource('javelin-behavior-phabricator-object-selector');

    $action = new AphrontHeadsupActionView();
    $action->setName('Merge Duplicates');
    $action->setURI('/search/attach/'.$task->getPHID().'/TASK/merge/');
    $action->setWorkflow(true);
    $action->setClass('action-merge');
    $actions[] = $action;

    $action = new AphrontHeadsupActionView();
    $action->setName('Create Subtask');
    $action->setURI('/maniphest/task/create/?parent='.$task->getID());
    $action->setClass('action-branch');
    $actions[] = $action;


    $action = new AphrontHeadsupActionView();
    $action->setName('Edit Dependencies');
    $action->setURI('/search/attach/'.$task->getPHID().'/TASK/dependencies/');
    $action->setWorkflow(true);
    $action->setClass('action-dependencies');
    $actions[] = $action;

    $action = new AphrontHeadsupActionView();
    $action->setName('Edit Differential Revisions');
    $action->setURI('/search/attach/'.$task->getPHID().'/DREV/');
    $action->setWorkflow(true);
    $action->setClass('action-attach');
    $actions[] = $action;

    $action_list = new AphrontHeadsupActionListView();
    $action_list->setActions($actions);

    $panel =
      '<div class="maniphest-panel">'.
        $action_list->render().
        '<div class="maniphest-task-detail-core">'.
          '<h1>'.
            '<span class="aphront-headsup-object-name">'.
              phutil_escape_html('T'.$task->getID()).
            '</span>'.
            ' '.
            phutil_escape_html($task->getTitle()).
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

    $draft = id(new PhabricatorDraft())->loadOneWhere(
      'authorPHID = %s AND draftKey = %s',
      $user->getPHID(),
      $task->getPHID());
    if ($draft) {
      $draft_text = $draft->getDraft();
    } else {
      $draft_text = null;
    }

    $panel_id = celerity_generate_unique_node_id();

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
          ->setValue($draft_text)
          ->setID('transaction-comments'))
      ->appendChild(
        id(new AphrontFormDragAndDropUploadControl())
          ->setLabel('Attached Files')
          ->setName('files')
          ->setDragAndDropTarget($panel_id)
          ->setActivatedClass('aphront-panel-view-drag-and-drop'))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue('Avast!'));

    $control_map = array(
      ManiphestTransactionType::TYPE_STATUS   => 'resolution',
      ManiphestTransactionType::TYPE_OWNER    => 'assign_to',
      ManiphestTransactionType::TYPE_CCS      => 'ccs',
      ManiphestTransactionType::TYPE_PRIORITY => 'priority',
      ManiphestTransactionType::TYPE_PROJECTS => 'projects',
      ManiphestTransactionType::TYPE_ATTACH   => 'file',
    );

    Javelin::initBehavior('maniphest-transaction-controls', array(
      'select' => 'transaction-action',
      'controlMap' => $control_map,
      'tokenizers' => array(
        ManiphestTransactionType::TYPE_PROJECTS => array(
          'id'       => 'projects-tokenizer',
          'src'      => '/typeahead/common/projects/',
          'ondemand' => PhabricatorEnv::getEnvConfig('tokenizer.ondemand'),
        ),
        ManiphestTransactionType::TYPE_OWNER => array(
          'id'       => 'assign-tokenizer',
          'src'      => '/typeahead/common/users/',
          'value'    => $default_claim,
          'limit'    => 1,
          'ondemand' => PhabricatorEnv::getEnvConfig('tokenizer.ondemand'),
        ),
        ManiphestTransactionType::TYPE_CCS => array(
          'id'       => 'cc-tokenizer',
          'src'      => '/typeahead/common/mailable/',
          'ondemand' => PhabricatorEnv::getEnvConfig('tokenizer.ondemand'),
        ),
      ),
    ));


    Javelin::initBehavior('maniphest-transaction-preview', array(
      'uri'     => '/maniphest/transaction/preview/'.$task->getID().'/',
      'preview' => 'transaction-preview',
      'comments' => 'transaction-comments',
      'action'   => 'transaction-action',
      'map'      => $control_map,
    ));

    $comment_panel = new AphrontPanelView();
    $comment_panel->appendChild($comment_form);
    $comment_panel->setID($panel_id);
    $comment_panel->addClass('aphront-panel-accent');
    $comment_panel->setHeader('Weigh In');

    $preview_panel =
      '<div class="aphront-panel-preview">
        <div id="transaction-preview">
          <div class="aphront-panel-preview-loading-text">
            Loading preview...
          </div>
        </div>
      </div>';

    $transaction_view = new ManiphestTransactionListView();
    $transaction_view->setTransactions($transactions);
    $transaction_view->setHandles($handles);
    $transaction_view->setUser($user);
    $transaction_view->setMarkupEngine($engine);

    return $this->buildStandardPageResponse(
      array(
        $context_bar,
        $panel,
        $transaction_view,
        $comment_panel,
        $preview_panel,
      ),
      array(
        'title' => 'T'.$task->getID().' '.$task->getTitle(),
      ));
  }

}
