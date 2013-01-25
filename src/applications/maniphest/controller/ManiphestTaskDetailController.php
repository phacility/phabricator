<?php

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
    $edges = idx($query->execute(), $phid);
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
    $this->loadHandles($phids);

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
        $this->getHandle($parent_task->getPHID())->renderLink().
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

    $engine = new PhabricatorMarkupEngine();
    $engine->setViewer($user);
    $engine->addObject($task, ManiphestTask::MARKUP_FIELD_DESCRIPTION);
    foreach ($transactions as $xaction) {
      if ($xaction->hasComments()) {
        $engine->addObject($xaction, ManiphestTransaction::MARKUP_FIELD_BODY);
      }
    }
    $engine->process();

    $extensions = ManiphestTaskExtensions::newExtensions();
    $aux_fields = $extensions->getAuxiliaryFieldSpecifications();
    if ($aux_fields) {
      $task->loadAndAttachAuxiliaryAttributes();
    }

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
          ->setID('transaction-comments')
          ->setUser($user))
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

    $comment_header = id(new PhabricatorHeaderView())
      ->setHeader($is_serious ? pht('Add Comment') : pht('Weigh In'));

    $preview_panel =
      '<div class="aphront-panel-preview">
        <div id="transaction-preview">
          <div class="aphront-panel-preview-loading-text">
            '.pht('Loading preview...').'
          </div>
        </div>
      </div>';

    $transaction_view = new ManiphestTransactionListView();
    $transaction_view->setTransactions($transactions);
    $transaction_view->setHandles($this->getLoadedHandles());
    $transaction_view->setUser($user);
    $transaction_view->setAuxiliaryFields($aux_fields);
    $transaction_view->setMarkupEngine($engine);

    PhabricatorFeedStoryNotification::updateObjectNotificationViews(
      $user, $task->getPHID());

    $object_name = 'T'.$task->getID();

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName($object_name)
        ->setHref('/'.$object_name));

    $header = $this->buildHeaderView($task);
    $actions = $this->buildActionView($task);
    $properties = $this->buildPropertyView($task, $aux_fields, $edges, $engine);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $context_bar,
        $header,
        $actions,
        $properties,
        $transaction_view,
        $comment_header,
        $comment_form,
        $preview_panel,
      ),
      array(
        'title' => 'T'.$task->getID().' '.$task->getTitle(),
        'pageObjects' => array($task->getPHID()),
        'device' => true,
      ));
  }

  private function buildHeaderView(ManiphestTask $task) {
    $view = id(new PhabricatorHeaderView())
      ->setObjectName('T'.$task->getID())
      ->setHeader($task->getTitle());

    $status = $task->getStatus();
    $status_name = ManiphestTaskStatus::getTaskStatusFullName($status);
    $status_color = ManiphestTaskStatus::getTaskStatusTagColor($status);

    $view->addTag(
      id(new PhabricatorTagView())
        ->setType(PhabricatorTagView::TYPE_STATE)
        ->setName($status_name)
        ->setBackgroundColor($status_color));

    return $view;
  }


  private function buildActionView(ManiphestTask $task) {
    $viewer = $this->getRequest()->getUser();

    $id = $task->getID();
    $phid = $task->getPHID();

    $view = new PhabricatorActionListView();
    $view->setUser($viewer);
    $view->setObject($task);

    $view->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Task'))
        ->setIcon('edit')
        ->setHref($this->getApplicationURI("/task/edit/{$id}/")));

    $view->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Merge Duplicates'))
        ->setHref("/search/attach/{$phid}/TASK/merge/")
        ->setWorkflow(true)
        ->setWorkflow(true)
        ->setIcon('merge'));

    $view->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Create Subtask'))
        ->setHref($this->getApplicationURI("/task/create/?parent={$id}"))
        ->setIcon('fork'));

    $view->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Dependencies'))
        ->setHref("/search/attach/{$phid}/TASK/dependencies/")
        ->setWorkflow(true)
        ->setIcon('link'));

    $view->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Differential Revisions'))
        ->setHref("/search/attach/{$phid}/DREV/")
        ->setWorkflow(true)
        ->setIcon('attach'));

    return $view;
  }

  private function buildPropertyView(
    ManiphestTask $task,
    array $aux_fields,
    array $edges,
    PhabricatorMarkupEngine $engine) {

    $viewer = $this->getRequest()->getUser();

    $view = new PhabricatorPropertyListView();

    $view->addProperty(
      pht('Assigned To'),
      $task->getOwnerPHID()
        ? $this->getHandle($task->getOwnerPHID())->renderLink()
        : '<em>'.pht('None').'</em>');

    $view->addProperty(
      pht('Priority'),
      phutil_escape_html(
        ManiphestTaskPriority::getTaskPriorityName($task->getPriority())));

    $view->addProperty(
      pht('Subscribers'),
      $task->getCCPHIDs()
        ? $this->renderHandlesForPHIDs($task->getCCPHIDs(), ',')
        : '<em>'.pht('None').'</em>');

    $view->addProperty(
      pht('Author'),
      $this->getHandle($task->getAuthorPHID())->renderLink());

    $source = $task->getOriginalEmailSource();
    if ($source) {
      $subject = '[T'.$task->getID().'] '.$task->getTitle();
      $view->addProperty(
        pht('From Email'),
        phutil_render_tag(
          'a',
          array(
            'href' => 'mailto:'.$source.'?subject='.$subject
            ),
          phutil_escape_html($source)));
    }

    $view->addProperty(
      pht('Projects'),
      $task->getProjectPHIDs()
        ? $this->renderHandlesForPHIDs($task->getProjectPHIDs(), ',')
        : '<em>'.pht('None').'</em>');

    foreach ($aux_fields as $aux_field) {
      $aux_key = $aux_field->getAuxiliaryKey();
      $aux_field->setValue($task->getAuxiliaryAttribute($aux_key));
      $value = $aux_field->renderForDetailView();
      if (strlen($value)) {
        $view->addProperty($aux_field->getLabel(), $value);
      }
    }


    $edge_types = array(
      PhabricatorEdgeConfig::TYPE_TASK_DEPENDED_ON_BY_TASK
        => pht('Dependent Tasks'),
      PhabricatorEdgeConfig::TYPE_TASK_DEPENDS_ON_TASK
        => pht('Depends On'),
      PhabricatorEdgeConfig::TYPE_TASK_HAS_RELATED_DREV
        => pht('Differential Revisions'),
      PhabricatorEdgeConfig::TYPE_TASK_HAS_COMMIT
        => pht('Commits'),
    );

    foreach ($edge_types as $edge_type => $edge_name) {
      if ($edges[$edge_type]) {
        $view->addProperty(
          $edge_name,
          $this->renderHandlesForPHIDs(array_keys($edges[$edge_type])));
      }
    }

    $attached = $task->getAttached();
    $file_infos = idx($attached, PhabricatorPHIDConstants::PHID_TYPE_FILE);
    if ($file_infos) {
      $file_phids = array_keys($file_infos);

      $files = id(new PhabricatorFile())->loadAllWhere(
        'phid IN (%Ls)',
        $file_phids);

      $file_view = new PhabricatorFileLinkListView();
      $file_view->setFiles($files);

      $view->addProperty(
        pht('Files'),
        $file_view->render());
    }

    if (strlen($task->getDescription())) {
      $view->addSectionHeader(pht('Description'));
      $view->addTextContent(
        phutil_render_tag(
          'div',
          array(
            'class' => 'phabricator-remarkup',
          ),
          $engine->getOutput($task, ManiphestTask::MARKUP_FIELD_DESCRIPTION)));
    }

    return $view;
  }


}
