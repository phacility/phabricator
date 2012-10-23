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
final class ManiphestTaskDetailController extends ManiphestController {

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

    $transactions = id(new ManiphestTransaction())->loadAllWhere(
      'taskID = %d ORDER BY id ASC',
      $task->getID());

    $e_commit = PhabricatorEdgeConfig::TYPE_TASK_HAS_COMMIT;
    $e_dep_on = PhabricatorEdgeConfig::TYPE_TASK_DEPENDS_ON_TASK;
    $e_dep_by = PhabricatorEdgeConfig::TYPE_TASK_DEPENDED_ON_BY_TASK;
    $e_rev    = PhabricatorEdgeConfig::TYPE_TASK_HAS_RELATED_DREV;

    $phid = $task->getPHID();

    $query = id(new PhabricatorEdgeQuery())
      ->withSourcePHIDs(array($phid))
      ->withEdgeTypes(
        array(
          $e_commit,
          $e_dep_on,
          $e_dep_by,
          $e_rev,
        ));
    $edges = $query->execute();

    $commit_phids = array_keys($edges[$phid][$e_commit]);
    $dep_on_tasks = array_keys($edges[$phid][$e_dep_on]);
    $dep_by_tasks = array_keys($edges[$phid][$e_dep_by]);
    $revs         = array_keys($edges[$phid][$e_rev]);

    $phids = array_fill_keys($query->getDestinationPHIDs(), true);

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

    $handles = $this->loadViewerHandles($phids);

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

    $extensions = ManiphestTaskExtensions::newExtensions();
    $aux_fields = $extensions->getAuxiliaryFieldSpecifications();
    if ($aux_fields) {
      $task->loadAndAttachAuxiliaryAttributes();
      foreach ($aux_fields as $aux_field) {
        $aux_key = $aux_field->getAuxiliaryKey();
        $aux_field->setValue($task->getAuxiliaryAttribute($aux_key));
        $value = $aux_field->renderForDetailView();
        if (strlen($value)) {
          $dict[$aux_field->getLabel()] = $value;
        }
      }
    }

    if ($dep_by_tasks) {
      $dict['Dependent Tasks'] = $this->renderHandleList(
        array_select_keys($handles, $dep_by_tasks));
    }

    if ($dep_on_tasks) {
      $dict['Depends On'] = $this->renderHandleList(
        array_select_keys($handles, $dep_on_tasks));
    }

    if ($revs) {
      $dict['Revisions'] = $this->renderHandleList(
        array_select_keys($handles, $revs));
    }

    if ($commit_phids) {
      $dict['Commits'] = $this->renderHandleList(
        array_select_keys($handles, $commit_phids));
    }

    $file_infos = idx($attached, PhabricatorPHIDConstants::PHID_TYPE_FILE);
    if ($file_infos) {
      $file_phids = array_keys($file_infos);

      $files = id(new PhabricatorFile())->loadAllWhere(
        'phid IN (%Ls)',
        $file_phids);

      $view = new PhabricatorFileLinkListView();
      $view->setFiles($files);

      $dict['Files'] = $view->render();
    }

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
      $context_bar->addButton('<label>Create Another:</label>');
      $context_bar->addButton(
         phutil_render_tag(
         'a',
         array(
           'href' => '/maniphest/task/create/?template='.$task->getID(),
           'class' => 'green button',
         ),
        'Similar Task'));
      $context_bar->addButton(
         phutil_render_tag(
         'a',
         array(
           'href' => '/maniphest/task/create/',
           'class' => 'green button',
         ),
        'Empty Task'));
      $context_bar->appendChild('New task created.');
    }

    $actions = array();

    $action = new AphrontHeadsupActionView();
    $action->setName('Edit Task');
    $action->setURI('/maniphest/task/edit/'.$task->getID().'/');
    $action->setClass('action-edit');
    $actions[] = $action;

    require_celerity_resource('phabricator-flag-css');
    $flag = PhabricatorFlagQuery::loadUserFlag($user, $task->getPHID());
    if ($flag) {
      $class = PhabricatorFlagColor::getCSSClass($flag->getColor());
      $color = PhabricatorFlagColor::getColorName($flag->getColor());

      $action = new AphrontHeadsupActionView();
      $action->setClass('flag-clear '.$class);
      $action->setURI('/flag/delete/'.$flag->getID().'/');
      $action->setName('Remove '.$color.' Flag');
      $action->setWorkflow(true);
      $actions[] = $action;
    } else {
      $action = new AphrontHeadsupActionView();
      $action->setClass('phabricator-flag-ghost');
      $action->setURI('/flag/edit/'.$task->getPHID().'/');
      $action->setName('Flag Task');
      $action->setWorkflow(true);
      $actions[] = $action;
    }

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

    $headsup_panel = new AphrontHeadsupView();
    $headsup_panel->setObjectName('T'.$task->getID());
    $headsup_panel->setHeader($task->getTitle());
    $headsup_panel->setActionList($action_list);
    $headsup_panel->setProperties($dict);

    $engine = new PhabricatorMarkupEngine();
    $engine->setViewer($user);
    $engine->addObject($task, ManiphestTask::MARKUP_FIELD_DESCRIPTION);
    foreach ($transactions as $xaction) {
      if ($xaction->hasComments()) {
        $engine->addObject($xaction, ManiphestTransaction::MARKUP_FIELD_BODY);
      }
    }
    $engine->process();

    $headsup_panel->appendChild(
      '<div class="phabricator-remarkup">'.
        $engine->getOutput($task, ManiphestTask::MARKUP_FIELD_DESCRIPTION).
      '</div>');

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

    $is_serious = PhabricatorEnv::getEnvConfig('phabricator.serious-business');

    if ($is_serious) {
      // Prevent tasks from being closed "out of spite" in serious business
      // installs.
      unset($resolution_types[ManiphestTaskStatus::STATUS_CLOSED_SPITE]);
    }

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
        id(new PhabricatorRemarkupControl())
          ->setLabel('Comments')
          ->setName('comments')
          ->setValue($draft_text)
          ->setID('transaction-comments'))
      ->appendChild(
        id(new AphrontFormDragAndDropUploadControl())
          ->setLabel('Attached Files')
          ->setName('files')
          ->setActivatedClass('aphront-panel-view-drag-and-drop'))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue($is_serious ? 'Submit' : 'Avast!'));

    $control_map = array(
      ManiphestTransactionType::TYPE_STATUS   => 'resolution',
      ManiphestTransactionType::TYPE_OWNER    => 'assign_to',
      ManiphestTransactionType::TYPE_CCS      => 'ccs',
      ManiphestTransactionType::TYPE_PRIORITY => 'priority',
      ManiphestTransactionType::TYPE_PROJECTS => 'projects',
      ManiphestTransactionType::TYPE_ATTACH   => 'file',
    );

    $tokenizer_map = array(
      ManiphestTransactionType::TYPE_PROJECTS => array(
        'id'          => 'projects-tokenizer',
        'src'         => '/typeahead/common/projects/',
        'ondemand'    => PhabricatorEnv::getEnvConfig('tokenizer.ondemand'),
        'placeholder' => 'Type a project name...',
      ),
      ManiphestTransactionType::TYPE_OWNER => array(
        'id'          => 'assign-tokenizer',
        'src'         => '/typeahead/common/users/',
        'value'       => $default_claim,
        'limit'       => 1,
        'ondemand'    => PhabricatorEnv::getEnvConfig('tokenizer.ondemand'),
        'placeholder' => 'Type a user name...',
      ),
      ManiphestTransactionType::TYPE_CCS => array(
        'id'          => 'cc-tokenizer',
        'src'         => '/typeahead/common/mailable/',
        'ondemand'    => PhabricatorEnv::getEnvConfig('tokenizer.ondemand'),
        'placeholder' => 'Type a user or mailing list...',
      ),
    );

    Javelin::initBehavior('maniphest-transaction-controls', array(
      'select'     => 'transaction-action',
      'controlMap' => $control_map,
      'tokenizers' => $tokenizer_map,
    ));

    Javelin::initBehavior('maniphest-transaction-preview', array(
      'uri'        => '/maniphest/transaction/preview/'.$task->getID().'/',
      'preview'    => 'transaction-preview',
      'comments'   => 'transaction-comments',
      'action'     => 'transaction-action',
      'map'        => $control_map,
      'tokenizers' => $tokenizer_map,
    ));

    $comment_panel = new AphrontPanelView();
    $comment_panel->appendChild($comment_form);
    $comment_panel->addClass('aphront-panel-accent');
    $comment_panel->setHeader($is_serious ? 'Add Comment' : 'Weigh In');

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
    $transaction_view->setAuxiliaryFields($aux_fields);
    $transaction_view->setMarkupEngine($engine);

    PhabricatorFeedStoryNotification::updateObjectNotificationViews(
      $user, $task->getPHID());

    return $this->buildStandardPageResponse(
      array(
        $context_bar,
        $headsup_panel,
        $transaction_view,
        $comment_panel,
        $preview_panel,
      ),
      array(
        'title' => 'T'.$task->getID().' '.$task->getTitle(),
        'pageObjects' => array($task->getPHID()),
      ));
  }

  private function renderHandleList(array $handles) {
    $links = array();
    foreach ($handles as $handle) {
      $links[] = $handle->renderLink();
    }
    return implode('<br />', $links);
  }

}
