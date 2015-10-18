<?php

final class ManiphestTaskDetailController extends ManiphestController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $id = $request->getURIData('id');

    $e_title = null;

    $priority_map = ManiphestTaskPriority::getTaskPriorityMap();

    $task = id(new ManiphestTaskQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->needSubscriberPHIDs(true)
      ->executeOne();
    if (!$task) {
      return new Aphront404Response();
    }

    $workflow = $request->getStr('workflow');
    $parent_task = null;
    if ($workflow && is_numeric($workflow)) {
      $parent_task = id(new ManiphestTaskQuery())
        ->setViewer($viewer)
        ->withIDs(array($workflow))
        ->executeOne();
    }

    $field_list = PhabricatorCustomField::getObjectFields(
      $task,
      PhabricatorCustomField::ROLE_VIEW);
    $field_list
      ->setViewer($viewer)
      ->readFieldsFromStorage($task);

    $e_commit = ManiphestTaskHasCommitEdgeType::EDGECONST;
    $e_dep_on = ManiphestTaskDependsOnTaskEdgeType::EDGECONST;
    $e_dep_by = ManiphestTaskDependedOnByTaskEdgeType::EDGECONST;
    $e_rev    = ManiphestTaskHasRevisionEdgeType::EDGECONST;
    $e_mock   = ManiphestTaskHasMockEdgeType::EDGECONST;

    $phid = $task->getPHID();

    $query = id(new PhabricatorEdgeQuery())
      ->withSourcePHIDs(array($phid))
      ->withEdgeTypes(
        array(
          $e_commit,
          $e_dep_on,
          $e_dep_by,
          $e_rev,
          $e_mock,
        ));
    $edges = idx($query->execute(), $phid);
    $phids = array_fill_keys($query->getDestinationPHIDs(), true);

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
    $handles = $viewer->loadHandles($phids);

    $info_view = null;
    if ($parent_task) {
      $info_view = new PHUIInfoView();
      $info_view->setSeverity(PHUIInfoView::SEVERITY_NOTICE);
      $info_view->addButton(
        id(new PHUIButtonView())
          ->setTag('a')
          ->setHref('/maniphest/task/create/?parent='.$parent_task->getID())
          ->setText(pht('Create Another Subtask')));

      $info_view->appendChild(hsprintf(
        'Created a subtask of <strong>%s</strong>.',
        $handles->renderHandle($parent_task->getPHID())));
    } else if ($workflow == 'create') {
      $info_view = new PHUIInfoView();
      $info_view->setSeverity(PHUIInfoView::SEVERITY_NOTICE);
      $info_view->addButton(
        id(new PHUIButtonView())
          ->setTag('a')
          ->setHref('/maniphest/task/create/?template='.$task->getID())
          ->setText(pht('Similar Task')));
      $info_view->addButton(
        id(new PHUIButtonView())
          ->setTag('a')
          ->setHref('/maniphest/task/create/')
          ->setText(pht('Empty Task')));
      $info_view->appendChild(pht('New task created. Create another?'));
    }

    $engine = new PhabricatorMarkupEngine();
    $engine->setViewer($viewer);
    $engine->setContextObject($task);
    $engine->addObject($task, ManiphestTask::MARKUP_FIELD_DESCRIPTION);

    $timeline = $this->buildTransactionTimeline(
      $task,
      new ManiphestTransactionQuery(),
      $engine);

    $resolution_types = ManiphestTaskStatus::getTaskStatusMap();

    $transaction_types = array(
      PhabricatorTransactions::TYPE_COMMENT     => pht('Comment'),
      ManiphestTransaction::TYPE_STATUS         => pht('Change Status'),
      ManiphestTransaction::TYPE_OWNER          => pht('Reassign / Claim'),
      PhabricatorTransactions::TYPE_SUBSCRIBERS => pht('Add CCs'),
      ManiphestTransaction::TYPE_PRIORITY       => pht('Change Priority'),
      PhabricatorTransactions::TYPE_EDGE        => pht('Associate Projects'),
    );

    // Remove actions the user doesn't have permission to take.

    $requires = array(
      ManiphestTransaction::TYPE_OWNER =>
        ManiphestEditAssignCapability::CAPABILITY,
      ManiphestTransaction::TYPE_PRIORITY =>
        ManiphestEditPriorityCapability::CAPABILITY,
      PhabricatorTransactions::TYPE_EDGE =>
        ManiphestEditProjectsCapability::CAPABILITY,
      ManiphestTransaction::TYPE_STATUS =>
        ManiphestEditStatusCapability::CAPABILITY,
    );

    foreach ($transaction_types as $type => $name) {
      if (isset($requires[$type])) {
        if (!$this->hasApplicationCapability($requires[$type])) {
          unset($transaction_types[$type]);
        }
      }
    }

    // Don't show an option to change to the current status, or to change to
    // the duplicate status explicitly.
    unset($resolution_types[$task->getStatus()]);
    unset($resolution_types[ManiphestTaskStatus::getDuplicateStatus()]);

    // Don't show owner/priority changes for closed tasks, as they don't make
    // much sense.
    if ($task->isClosed()) {
      unset($transaction_types[ManiphestTransaction::TYPE_PRIORITY]);
      unset($transaction_types[ManiphestTransaction::TYPE_OWNER]);
    }

    $default_claim = array(
      $viewer->getPHID() => $viewer->getUsername().
        ' ('.$viewer->getRealName().')',
    );

    $draft = id(new PhabricatorDraft())->loadOneWhere(
      'authorPHID = %s AND draftKey = %s',
      $viewer->getPHID(),
      $task->getPHID());
    if ($draft) {
      $draft_text = $draft->getDraft();
    } else {
      $draft_text = null;
    }

    $projects_source = new PhabricatorProjectDatasource();
    $users_source = new PhabricatorPeopleDatasource();
    $mailable_source = new PhabricatorMetaMTAMailableDatasource();

    $comment_form = new AphrontFormView();
    $comment_form
      ->setUser($viewer)
      ->setWorkflow(true)
      ->setAction('/maniphest/transaction/save/')
      ->setEncType('multipart/form-data')
      ->addHiddenInput('taskID', $task->getID())
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel(pht('Action'))
          ->setName('action')
          ->setOptions($transaction_types)
          ->setID('transaction-action'))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel(pht('Status'))
          ->setName('resolution')
          ->setControlID('resolution')
          ->setControlStyle('display: none')
          ->setOptions($resolution_types))
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setLabel(pht('Assign To'))
          ->setName('assign_to')
          ->setControlID('assign_to')
          ->setControlStyle('display: none')
          ->setID('assign-tokenizer')
          ->setDisableBehavior(true)
          ->setDatasource($users_source))
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setLabel(pht('CCs'))
          ->setName('ccs')
          ->setControlID('ccs')
          ->setControlStyle('display: none')
          ->setID('cc-tokenizer')
          ->setDisableBehavior(true)
          ->setDatasource($mailable_source))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel(pht('Priority'))
          ->setName('priority')
          ->setOptions($priority_map)
          ->setControlID('priority')
          ->setControlStyle('display: none')
          ->setValue($task->getPriority()))
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setLabel(pht('Projects'))
          ->setName('projects')
          ->setControlID('projects')
          ->setControlStyle('display: none')
          ->setID('projects-tokenizer')
          ->setDisableBehavior(true)
          ->setDatasource($projects_source))
      ->appendChild(
        id(new AphrontFormFileControl())
          ->setLabel(pht('File'))
          ->setName('file')
          ->setControlID('file')
          ->setControlStyle('display: none'))
      ->appendChild(
        id(new PhabricatorRemarkupControl())
          ->setUser($viewer)
          ->setLabel(pht('Comments'))
          ->setName('comments')
          ->setValue($draft_text)
          ->setID('transaction-comments')
          ->setUser($viewer))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Submit')));

    $control_map = array(
      ManiphestTransaction::TYPE_STATUS         => 'resolution',
      ManiphestTransaction::TYPE_OWNER          => 'assign_to',
      PhabricatorTransactions::TYPE_SUBSCRIBERS => 'ccs',
      ManiphestTransaction::TYPE_PRIORITY       => 'priority',
      PhabricatorTransactions::TYPE_EDGE        => 'projects',
    );

    $tokenizer_map = array(
      PhabricatorTransactions::TYPE_EDGE => array(
        'id'          => 'projects-tokenizer',
        'src'         => $projects_source->getDatasourceURI(),
        'placeholder' => $projects_source->getPlaceholderText(),
      ),
      ManiphestTransaction::TYPE_OWNER => array(
        'id'          => 'assign-tokenizer',
        'src'         => $users_source->getDatasourceURI(),
        'value'       => $default_claim,
        'limit'       => 1,
        'placeholder' => $users_source->getPlaceholderText(),
      ),
      PhabricatorTransactions::TYPE_SUBSCRIBERS => array(
        'id'          => 'cc-tokenizer',
        'src'         => $mailable_source->getDatasourceURI(),
        'placeholder' => $mailable_source->getPlaceholderText(),
      ),
    );

    // TODO: Initializing these behaviors for logged out users fatals things.
    if ($viewer->isLoggedIn()) {
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
    }

    $is_serious = PhabricatorEnv::getEnvConfig('phabricator.serious-business');
    $comment_header = $is_serious
      ? pht('Add Comment')
      : pht('Weigh In');

    $preview_panel = phutil_tag_div(
      'aphront-panel-preview',
      phutil_tag(
        'div',
        array('id' => 'transaction-preview'),
        phutil_tag_div(
          'aphront-panel-preview-loading-text',
          pht('Loading preview...'))));

    $object_name = 'T'.$task->getID();
    $actions = $this->buildActionView($task);

    $crumbs = $this->buildApplicationCrumbs()
      ->addTextCrumb($object_name, '/'.$object_name);

    $header = $this->buildHeaderView($task);
    $properties = $this->buildPropertyView(
      $task, $field_list, $edges, $actions, $handles);
    $description = $this->buildDescriptionView($task, $engine);

    if (!$viewer->isLoggedIn()) {
      // TODO: Eventually, everything should run through this. For now, we're
      // only using it to get a consistent "Login to Comment" button.
      $comment_box = id(new PhabricatorApplicationTransactionCommentView())
        ->setUser($viewer)
        ->setRequestURI($request->getRequestURI());
      $preview_panel = null;
    } else {
      $comment_box = id(new PHUIObjectBoxView())
        ->setFlush(true)
        ->setHeaderText($comment_header)
        ->setForm($comment_form);
      $timeline->setQuoteTargetID('transaction-comments');
      $timeline->setQuoteRef($object_name);
    }

    $object_box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($properties);

    if ($description) {
      $object_box->addPropertyList($description);
    }

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $info_view,
        $object_box,
        $timeline,
        $comment_box,
        $preview_panel,
      ),
      array(
        'title' => 'T'.$task->getID().' '.$task->getTitle(),
        'pageObjects' => array($task->getPHID()),
      ));
  }

  private function buildHeaderView(ManiphestTask $task) {
    $view = id(new PHUIHeaderView())
      ->setHeader($task->getTitle())
      ->setUser($this->getRequest()->getUser())
      ->setPolicyObject($task);

    $status = $task->getStatus();
    $status_name = ManiphestTaskStatus::renderFullDescription($status);

    $view->addProperty(PHUIHeaderView::PROPERTY_STATUS, $status_name);

    return $view;
  }


  private function buildActionView(ManiphestTask $task) {
    $viewer = $this->getRequest()->getUser();

    $id = $task->getID();
    $phid = $task->getPHID();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $task,
      PhabricatorPolicyCapability::CAN_EDIT);

    $can_create = $viewer->isLoggedIn();

    $view = id(new PhabricatorActionListView())
      ->setUser($viewer)
      ->setObject($task)
      ->setObjectURI($this->getRequest()->getRequestURI());

    $view->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Task'))
        ->setIcon('fa-pencil')
        ->setHref($this->getApplicationURI("/task/edit/{$id}/"))
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    $view->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Merge Duplicates In'))
        ->setHref("/search/attach/{$phid}/TASK/merge/")
        ->setWorkflow(true)
        ->setIcon('fa-compress')
        ->setDisabled(!$can_edit)
        ->setWorkflow(true));

    $view->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Create Subtask'))
        ->setHref($this->getApplicationURI("/task/create/?parent={$id}"))
        ->setIcon('fa-level-down')
        ->setDisabled(!$can_create)
        ->setWorkflow(!$can_create));

    $view->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Blocking Tasks'))
        ->setHref("/search/attach/{$phid}/TASK/blocks/")
        ->setWorkflow(true)
        ->setIcon('fa-link')
        ->setDisabled(!$can_edit)
        ->setWorkflow(true));

    return $view;
  }

  private function buildPropertyView(
    ManiphestTask $task,
    PhabricatorCustomFieldList $field_list,
    array $edges,
    PhabricatorActionListView $actions,
    $handles) {

    $viewer = $this->getRequest()->getUser();

    $view = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setObject($task)
      ->setActionList($actions);

    $view->addProperty(
      pht('Assigned To'),
      $task->getOwnerPHID()
        ? $handles->renderHandle($task->getOwnerPHID())
        : phutil_tag('em', array(), pht('None')));

    $view->addProperty(
      pht('Priority'),
      ManiphestTaskPriority::getTaskPriorityName($task->getPriority()));

    $view->addProperty(
      pht('Author'),
      $handles->renderHandle($task->getAuthorPHID()));

    $source = $task->getOriginalEmailSource();
    if ($source) {
      $subject = '[T'.$task->getID().'] '.$task->getTitle();
      $view->addProperty(
        pht('From Email'),
        phutil_tag(
          'a',
          array(
            'href' => 'mailto:'.$source.'?subject='.$subject,
          ),
          $source));
    }

    $edge_types = array(
      ManiphestTaskDependedOnByTaskEdgeType::EDGECONST
        => pht('Blocks'),
      ManiphestTaskDependsOnTaskEdgeType::EDGECONST
        => pht('Blocked By'),
      ManiphestTaskHasRevisionEdgeType::EDGECONST
        => pht('Differential Revisions'),
      ManiphestTaskHasMockEdgeType::EDGECONST
        => pht('Pholio Mocks'),
    );

    $revisions_commits = array();

    $commit_phids = array_keys(
      $edges[ManiphestTaskHasCommitEdgeType::EDGECONST]);
    if ($commit_phids) {
      $commit_drev = DiffusionCommitHasRevisionEdgeType::EDGECONST;
      $drev_edges = id(new PhabricatorEdgeQuery())
        ->withSourcePHIDs($commit_phids)
        ->withEdgeTypes(array($commit_drev))
        ->execute();

      foreach ($commit_phids as $phid) {
        $revisions_commits[$phid] = $handles->renderHandle($phid);
        $revision_phid = key($drev_edges[$phid][$commit_drev]);
        $revision_handle = $handles->getHandleIfExists($revision_phid);
        if ($revision_handle) {
          $task_drev = ManiphestTaskHasRevisionEdgeType::EDGECONST;
          unset($edges[$task_drev][$revision_phid]);
          $revisions_commits[$phid] = hsprintf(
            '%s / %s',
            $revision_handle->renderLink($revision_handle->getName()),
            $revisions_commits[$phid]);
        }
      }
    }

    foreach ($edge_types as $edge_type => $edge_name) {
      if ($edges[$edge_type]) {
        $edge_handles = $viewer->loadHandles(array_keys($edges[$edge_type]));
        $view->addProperty(
          $edge_name,
          $edge_handles->renderList());
      }
    }

    if ($revisions_commits) {
      $view->addProperty(
        pht('Commits'),
        phutil_implode_html(phutil_tag('br'), $revisions_commits));
    }

    $attached = $task->getAttached();
    if (!is_array($attached)) {
      $attached = array();
    }

    $file_infos = idx($attached, PhabricatorFileFilePHIDType::TYPECONST);
    if ($file_infos) {
      $file_phids = array_keys($file_infos);

      // TODO: These should probably be handles or something; clean this up
      // as we sort out file attachments.
      $files = id(new PhabricatorFileQuery())
        ->setViewer($viewer)
        ->withPHIDs($file_phids)
        ->execute();

      $file_view = new PhabricatorFileLinkListView();
      $file_view->setFiles($files);

      $view->addProperty(
        pht('Files'),
        $file_view->render());
    }

    $view->invokeWillRenderEvent();

    $field_list->appendFieldsToPropertyList(
      $task,
      $viewer,
      $view);

    return $view;
  }

  private function buildDescriptionView(
    ManiphestTask $task,
    PhabricatorMarkupEngine $engine) {

    $section = null;
    if (strlen($task->getDescription())) {
      $section = new PHUIPropertyListView();
      $section->addSectionHeader(
        pht('Description'),
        PHUIPropertyListView::ICON_SUMMARY);
      $section->addTextContent(
        phutil_tag(
          'div',
          array(
            'class' => 'phabricator-remarkup',
          ),
          $engine->getOutput($task, ManiphestTask::MARKUP_FIELD_DESCRIPTION)));
    }

    return $section;
  }

}
