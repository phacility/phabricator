<?php

final class ManiphestTaskDetailController extends ManiphestController {

  private $id;

  public function shouldAllowPublic() {
    return true;
  }

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    $e_title = null;

    $priority_map = ManiphestTaskPriority::getTaskPriorityMap();

    $task = id(new ManiphestTaskQuery())
      ->setViewer($user)
      ->withIDs(array($this->id))
      ->executeOne();
    if (!$task) {
      return new Aphront404Response();
    }

    $workflow = $request->getStr('workflow');
    $parent_task = null;
    if ($workflow && is_numeric($workflow)) {
      $parent_task = id(new ManiphestTaskQuery())
        ->setViewer($user)
        ->withIDs(array($workflow))
        ->executeOne();
    }

    $transactions = id(new ManiphestTransactionQuery())
      ->setViewer($user)
      ->withObjectPHIDs(array($task->getPHID()))
      ->needComments(true)
      ->execute();

    $field_list = PhabricatorCustomField::getObjectFields(
      $task,
      PhabricatorCustomField::ROLE_VIEW);
    $field_list
      ->setViewer($user)
      ->readFieldsFromStorage($task);

    $e_commit = PhabricatorEdgeConfig::TYPE_TASK_HAS_COMMIT;
    $e_dep_on = PhabricatorEdgeConfig::TYPE_TASK_DEPENDS_ON_TASK;
    $e_dep_by = PhabricatorEdgeConfig::TYPE_TASK_DEPENDED_ON_BY_TASK;
    $e_rev    = PhabricatorEdgeConfig::TYPE_TASK_HAS_RELATED_DREV;
    $e_mock   = PhabricatorEdgeConfig::TYPE_TASK_HAS_MOCK;

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

    $handles = $this->getLoadedHandles();

    $context_bar = null;

    if ($parent_task) {
      $context_bar = new AphrontContextBarView();
      $context_bar->addButton(phutil_tag(
      'a',
      array(
        'href' => '/maniphest/task/create/?parent='.$parent_task->getID(),
        'class' => 'green button',
      ),
      pht('Create Another Subtask')));
      $context_bar->appendChild(hsprintf(
        'Created a subtask of <strong>%s</strong>',
        $this->getHandle($parent_task->getPHID())->renderLink()));
    } else if ($workflow == 'create') {
      $context_bar = new AphrontContextBarView();
      $context_bar->addButton(phutil_tag('label', array(), 'Create Another'));
      $context_bar->addButton(phutil_tag(
        'a',
        array(
          'href' => '/maniphest/task/create/?template='.$task->getID(),
          'class' => 'green button',
        ),
        pht('Similar Task')));
      $context_bar->addButton(phutil_tag(
        'a',
        array(
          'href' => '/maniphest/task/create/',
          'class' => 'green button',
        ),
        pht('Empty Task')));
      $context_bar->appendChild(pht('New task created.'));
    }

    $engine = new PhabricatorMarkupEngine();
    $engine->setViewer($user);
    $engine->addObject($task, ManiphestTask::MARKUP_FIELD_DESCRIPTION);
    foreach ($transactions as $modern_xaction) {
      if ($modern_xaction->getComment()) {
        $engine->addObject(
          $modern_xaction->getComment(),
          PhabricatorApplicationTransactionComment::MARKUP_FIELD_COMMENT);
      }
    }

    $engine->process();

    $resolution_types = ManiphestTaskStatus::getTaskStatusMap();

    $transaction_types = array(
      PhabricatorTransactions::TYPE_COMMENT => pht('Comment'),
      ManiphestTransaction::TYPE_STATUS     => pht('Change Status'),
      ManiphestTransaction::TYPE_OWNER      => pht('Reassign / Claim'),
      ManiphestTransaction::TYPE_CCS        => pht('Add CCs'),
      ManiphestTransaction::TYPE_PRIORITY   => pht('Change Priority'),
      ManiphestTransaction::TYPE_ATTACH     => pht('Upload File'),
      ManiphestTransaction::TYPE_PROJECTS   => pht('Associate Projects'),
    );

    // Remove actions the user doesn't have permission to take.

    $requires = array(
      ManiphestTransaction::TYPE_OWNER =>
        ManiphestCapabilityEditAssign::CAPABILITY,
      ManiphestTransaction::TYPE_PRIORITY =>
        ManiphestCapabilityEditPriority::CAPABILITY,
      ManiphestTransaction::TYPE_PROJECTS =>
        ManiphestCapabilityEditProjects::CAPABILITY,
      ManiphestTransaction::TYPE_STATUS =>
        ManiphestCapabilityEditStatus::CAPABILITY,
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

    $submit_text = $is_serious
      ? pht('Submit')
      : pht('Avast!');

    $close_text = $is_serious
      ? pht('Close Task')
      : pht('Scuttle Task');

    $submit_control = id(new PHUIFormMultiSubmitControl());
    if (!$task->isClosed()) {
      $close_image = id(new PHUIIconView())
          ->setSpriteSheet(PHUIIconView::SPRITE_ICONS)
          ->setSpriteIcon('check');
      $submit_control->addButtonView(
        id(new PHUIButtonView())
          ->setColor(PHUIButtonView::GREY)
          ->setIcon($close_image)
          ->setText($close_text)
          ->setName('scuttle')
          ->addSigil('alternate-submit-button'));
    }
    $submit_control->addSubmitButton($submit_text);

    $comment_form = new AphrontFormView();
    $comment_form
      ->setUser($user)
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
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setLabel(pht('Assign To'))
          ->setName('assign_to')
          ->setControlID('assign_to')
          ->setControlStyle('display: none')
          ->setID('assign-tokenizer')
          ->setDisableBehavior(true))
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setLabel(pht('CCs'))
          ->setName('ccs')
          ->setControlID('ccs')
          ->setControlStyle('display: none')
          ->setID('cc-tokenizer')
          ->setDisableBehavior(true))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel(pht('Priority'))
          ->setName('priority')
          ->setOptions($priority_map)
          ->setControlID('priority')
          ->setControlStyle('display: none')
          ->setValue($task->getPriority()))
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setLabel(pht('Projects'))
          ->setName('projects')
          ->setControlID('projects')
          ->setControlStyle('display: none')
          ->setID('projects-tokenizer')
          ->setDisableBehavior(true))
      ->appendChild(
        id(new AphrontFormFileControl())
          ->setLabel(pht('File'))
          ->setName('file')
          ->setControlID('file')
          ->setControlStyle('display: none'))
      ->appendChild(
        id(new PhabricatorRemarkupControl())
          ->setLabel(pht('Comments'))
          ->setName('comments')
          ->setValue($draft_text)
          ->setID('transaction-comments')
          ->setUser($user))
      ->appendChild($submit_control);

    $control_map = array(
      ManiphestTransaction::TYPE_STATUS   => 'resolution',
      ManiphestTransaction::TYPE_OWNER    => 'assign_to',
      ManiphestTransaction::TYPE_CCS      => 'ccs',
      ManiphestTransaction::TYPE_PRIORITY => 'priority',
      ManiphestTransaction::TYPE_PROJECTS => 'projects',
      ManiphestTransaction::TYPE_ATTACH   => 'file',
    );

    $tokenizer_map = array(
      ManiphestTransaction::TYPE_PROJECTS => array(
        'id'          => 'projects-tokenizer',
        'src'         => '/typeahead/common/projects/',
        'placeholder' => pht('Type a project name...'),
      ),
      ManiphestTransaction::TYPE_OWNER => array(
        'id'          => 'assign-tokenizer',
        'src'         => '/typeahead/common/users/',
        'value'       => $default_claim,
        'limit'       => 1,
        'placeholder' => pht('Type a user name...'),
      ),
      ManiphestTransaction::TYPE_CCS => array(
        'id'          => 'cc-tokenizer',
        'src'         => '/typeahead/common/mailable/',
        'placeholder' => pht('Type a user or mailing list...'),
      ),
    );

    // TODO: Initializing these behaviors for logged out users fatals things.
    if ($user->isLoggedIn()) {
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

    $timeline = id(new PhabricatorApplicationTransactionView())
      ->setUser($user)
      ->setObjectPHID($task->getPHID())
      ->setTransactions($transactions)
      ->setMarkupEngine($engine);

    $object_name = 'T'.$task->getID();
    $actions = $this->buildActionView($task);

    $crumbs = $this->buildApplicationCrumbs()
      ->addTextCrumb($object_name, '/'.$object_name)
      ->setActionList($actions);

    $header = $this->buildHeaderView($task);
    $properties = $this->buildPropertyView(
      $task, $field_list, $edges, $actions);
    $description = $this->buildDescriptionView($task, $engine);

    if (!$user->isLoggedIn()) {
      // TODO: Eventually, everything should run through this. For now, we're
      // only using it to get a consistent "Login to Comment" button.
      $comment_box = id(new PhabricatorApplicationTransactionCommentView())
        ->setUser($user)
        ->setRequestURI($request->getRequestURI());
      $preview_panel = null;
    } else {
      $comment_box = id(new PHUIObjectBoxView())
        ->setFlush(true)
        ->setHeaderText($comment_header)
        ->appendChild($comment_form);
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
        $context_bar,
        $object_box,
        $timeline,
        $comment_box,
        $preview_panel,
      ),
      array(
        'title' => 'T'.$task->getID().' '.$task->getTitle(),
        'pageObjects' => array($task->getPHID()),
        'device' => true,
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
    $viewer_phid = $viewer->getPHID();
    $viewer_is_cc = in_array($viewer_phid, $task->getCCPHIDs());

    $id = $task->getID();
    $phid = $task->getPHID();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $task,
      PhabricatorPolicyCapability::CAN_EDIT);

    $view = id(new PhabricatorActionListView())
      ->setUser($viewer)
      ->setObject($task)
      ->setObjectURI($this->getRequest()->getRequestURI());

    $view->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Task'))
        ->setIcon('edit')
        ->setHref($this->getApplicationURI("/task/edit/{$id}/"))
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    if ($task->getOwnerPHID() === $viewer_phid) {
      $view->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Automatically Subscribed'))
          ->setDisabled(true)
          ->setIcon('enable'));
    } else {
      $action = $viewer_is_cc ? 'rem' : 'add';
      $name   = $viewer_is_cc ? pht('Unsubscribe') : pht('Subscribe');
      $icon   = $viewer_is_cc ? 'disable' : 'check';

      $view->addAction(
        id(new PhabricatorActionView())
          ->setName($name)
          ->setHref("/maniphest/subscribe/{$action}/{$id}/")
          ->setRenderAsForm(true)
          ->setUser($viewer)
          ->setIcon($icon));
    }

    $view->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Merge Duplicates In'))
        ->setHref("/search/attach/{$phid}/TASK/merge/")
        ->setWorkflow(true)
        ->setIcon('merge')
        ->setDisabled(!$can_edit)
        ->setWorkflow(true));

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
        ->setIcon('link')
        ->setDisabled(!$can_edit)
        ->setWorkflow(true));

    return $view;
  }

  private function buildPropertyView(
    ManiphestTask $task,
    PhabricatorCustomFieldList $field_list,
    array $edges,
    PhabricatorActionListView $actions) {

    $viewer = $this->getRequest()->getUser();

    $view = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setObject($task)
      ->setActionList($actions);

    $view->addProperty(
      pht('Assigned To'),
      $task->getOwnerPHID()
      ? $this->getHandle($task->getOwnerPHID())->renderLink()
      : phutil_tag('em', array(), pht('None')));

    $view->addProperty(
      pht('Priority'),
      ManiphestTaskPriority::getTaskPriorityName($task->getPriority()));

    $handles = $this->getLoadedHandles();
    $cc_handles = array_select_keys($handles, $task->getCCPHIDs());
    $subscriber_html = id(new SubscriptionListStringBuilder())
      ->setObjectPHID($task->getPHID())
      ->setHandles($cc_handles)
      ->buildPropertyString();
    $view->addProperty(pht('Subscribers'), $subscriber_html);

    $view->addProperty(
      pht('Author'),
      $this->getHandle($task->getAuthorPHID())->renderLink());

    $source = $task->getOriginalEmailSource();
    if ($source) {
      $subject = '[T'.$task->getID().'] '.$task->getTitle();
      $view->addProperty(
        pht('From Email'),
        phutil_tag(
          'a',
          array(
            'href' => 'mailto:'.$source.'?subject='.$subject
          ),
          $source));
    }

    $project_phids = $task->getProjectPHIDs();
    if ($project_phids) {
      require_celerity_resource('maniphest-task-summary-css');

      // If we end up with real-world projects with many hundreds of columns, it
      // might be better to just load all the edges, then load those columns and
      // work backward that way, or denormalize this data more.

      $columns = id(new PhabricatorProjectColumnQuery())
        ->setViewer($viewer)
        ->withProjectPHIDs($project_phids)
        ->execute();
      $columns = mpull($columns, null, 'getPHID');

      $column_edge_type = PhabricatorEdgeConfig::TYPE_OBJECT_HAS_COLUMN;
      $all_column_phids = array_keys($columns);

      $column_edge_query = id(new PhabricatorEdgeQuery())
        ->withSourcePHIDs(array($task->getPHID()))
        ->withEdgeTypes(array($column_edge_type))
        ->withDestinationPHIDs($all_column_phids);
      $column_edge_query->execute();
      $in_column_phids = array_fuse($column_edge_query->getDestinationPHIDs());

      $column_groups = mgroup($columns, 'getProjectPHID');

      $project_rows = array();
      foreach ($project_phids as $project_phid) {
        $row = array();

        $handle = $this->getHandle($project_phid);
        $row[] = $handle->renderLink();

        $columns = idx($column_groups, $project_phid, array());
        $column = head(array_intersect_key($columns, $in_column_phids));
        if ($column) {
          $column_name = pht('(%s)', $column->getDisplayName());
          // TODO: This is really hacky but there's no cleaner way to do it
          // right now, T4022 should give us better tools for this.
          $column_href = str_replace(
            'project/view',
            'project/board',
            $handle->getURI());
          $column_link = phutil_tag(
            'a',
            array(
              'href' => $column_href,
              'class' => 'maniphest-board-link',
            ),
            $column_name);

          $row[] = ' ';
          $row[] = $column_link;
        }

        $project_rows[] = phutil_tag('div', array(), $row);
      }
    } else {
      $project_rows = phutil_tag('em', array(), pht('None'));
    }

    $view->addProperty(pht('Projects'), $project_rows);

    $edge_types = array(
      PhabricatorEdgeConfig::TYPE_TASK_DEPENDED_ON_BY_TASK
      => pht('Dependent Tasks'),
      PhabricatorEdgeConfig::TYPE_TASK_DEPENDS_ON_TASK
      => pht('Depends On'),
      PhabricatorEdgeConfig::TYPE_TASK_HAS_RELATED_DREV
      => pht('Differential Revisions'),
      PhabricatorEdgeConfig::TYPE_TASK_HAS_MOCK
      => pht('Pholio Mocks'),
    );

    $revisions_commits = array();
    $handles = $this->getLoadedHandles();

    $commit_phids = array_keys(
      $edges[PhabricatorEdgeConfig::TYPE_TASK_HAS_COMMIT]);
    if ($commit_phids) {
      $commit_drev = PhabricatorEdgeConfig::TYPE_COMMIT_HAS_DREV;
      $drev_edges = id(new PhabricatorEdgeQuery())
        ->withSourcePHIDs($commit_phids)
        ->withEdgeTypes(array($commit_drev))
        ->execute();

      foreach ($commit_phids as $phid) {
        $revisions_commits[$phid] = $handles[$phid]->renderLink();
        $revision_phid = key($drev_edges[$phid][$commit_drev]);
        $revision_handle = idx($handles, $revision_phid);
        if ($revision_handle) {
          $task_drev = PhabricatorEdgeConfig::TYPE_TASK_HAS_RELATED_DREV;
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
        $view->addProperty(
          $edge_name,
          $this->renderHandlesForPHIDs(array_keys($edges[$edge_type])));
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

    $file_infos = idx($attached, PhabricatorFilePHIDTypeFile::TYPECONST);
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

    $field_list->appendFieldsToPropertyList(
      $task,
      $viewer,
      $view);

    $view->invokeWillRenderEvent();

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
