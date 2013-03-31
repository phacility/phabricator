<?php

/**
 * @group maniphest
 */
final class ManiphestTaskEditController extends ManiphestController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = idx($data, 'id');
  }

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    $files = array();
    $parent_task = null;
    $template_id = null;

    if ($this->id) {
      $task = id(new ManiphestTask())->load($this->id);
      if (!$task) {
        return new Aphront404Response();
      }
    } else {
      $task = new ManiphestTask();
      $task->setPriority(ManiphestTaskPriority::getDefaultPriority());
      $task->setAuthorPHID($user->getPHID());

      // These allow task creation with defaults.
      if (!$request->isFormPost()) {
        $task->setTitle($request->getStr('title'));

        $default_projects = $request->getStr('projects');
        if ($default_projects) {
          $task->setProjectPHIDs(explode(';', $default_projects));
        }
      }

      $file_phids = $request->getArr('files', array());
      if (!$file_phids) {
        // Allow a single 'file' key instead, mostly since Mac OS X urlencodes
        // square brackets in URLs when passed to 'open', so you can't 'open'
        // a URL like '?files[]=xyz' and have PHP interpret it correctly.
        $phid = $request->getStr('file');
        if ($phid) {
          $file_phids = array($phid);
        }
      }

      if ($file_phids) {
        $files = id(new PhabricatorFile())->loadAllWhere(
          'phid IN (%Ls)',
          $file_phids);
      }

      $template_id = $request->getInt('template');

      // You can only have a parent task if you're creating a new task.
      $parent_id = $request->getInt('parent');
      if ($parent_id) {
        $parent_task = id(new ManiphestTask())->load($parent_id);
        if (!$template_id) {
          $template_id = $parent_id;
        }
      }
    }

    $errors = array();
    $e_title = true;

    $extensions = ManiphestTaskExtensions::newExtensions();
    $aux_fields = $extensions->loadFields($task, $user);

    if ($request->isFormPost()) {
      $changes = array();

      $new_title = $request->getStr('title');
      $new_desc = $request->getStr('description');
      $new_status = $request->getStr('status');

      $workflow = '';

      if ($task->getID()) {
        if ($new_title != $task->getTitle()) {
          $changes[ManiphestTransactionType::TYPE_TITLE] = $new_title;
        }
        if ($new_desc != $task->getDescription()) {
          $changes[ManiphestTransactionType::TYPE_DESCRIPTION] = $new_desc;
        }
        if ($new_status != $task->getStatus()) {
          $changes[ManiphestTransactionType::TYPE_STATUS] = $new_status;
        }
      } else {
        $task->setTitle($new_title);
        $task->setDescription($new_desc);
        $changes[ManiphestTransactionType::TYPE_STATUS] =
          ManiphestTaskStatus::STATUS_OPEN;

        $workflow = 'create';
      }

      $owner_tokenizer = $request->getArr('assigned_to');
      $owner_phid = reset($owner_tokenizer);

      if (!strlen($new_title)) {
        $e_title = pht('Required');
        $errors[] = pht('Title is required.');
      }

      foreach ($aux_fields as $aux_field) {
        $aux_field->setValueFromRequest($request);

        if ($aux_field->isRequired() && !$aux_field->getValue()) {
          $errors[] = pht('%s is required.', $aux_field->getLabel());
          $aux_field->setError(pht('Required'));
        }

        try {
          $aux_field->validate();
        } catch (Exception $e) {
          $errors[] = $e->getMessage();
          $aux_field->setError(pht('Invalid'));
        }
      }

      if ($errors) {
        $task->setPriority($request->getInt('priority'));
        $task->setOwnerPHID($owner_phid);
        $task->setCCPHIDs($request->getArr('cc'));
        $task->setProjectPHIDs($request->getArr('projects'));
      } else {
        if ($request->getInt('priority') != $task->getPriority()) {
          $changes[ManiphestTransactionType::TYPE_PRIORITY] =
            $request->getInt('priority');
        }

        if ($owner_phid != $task->getOwnerPHID()) {
          $changes[ManiphestTransactionType::TYPE_OWNER] = $owner_phid;
        }

        if ($request->getArr('cc') != $task->getCCPHIDs()) {
          $changes[ManiphestTransactionType::TYPE_CCS] = $request->getArr('cc');
        }

        $new_proj_arr = $request->getArr('projects');
        $new_proj_arr = array_values($new_proj_arr);
        sort($new_proj_arr);

        $cur_proj_arr = $task->getProjectPHIDs();
        $cur_proj_arr = array_values($cur_proj_arr);
        sort($cur_proj_arr);

        if ($new_proj_arr != $cur_proj_arr) {
          $changes[ManiphestTransactionType::TYPE_PROJECTS] = $new_proj_arr;
        }

        if ($files) {
          $file_map = mpull($files, 'getPHID');
          $file_map = array_fill_keys($file_map, array());
          $changes[ManiphestTransactionType::TYPE_ATTACH] = array(
            PhabricatorPHIDConstants::PHID_TYPE_FILE => $file_map,
          );
        }

        $content_source = PhabricatorContentSource::newForSource(
          PhabricatorContentSource::SOURCE_WEB,
          array(
            'ip' => $request->getRemoteAddr(),
          ));

        $template = new ManiphestTransaction();
        $template->setAuthorPHID($user->getPHID());
        $template->setContentSource($content_source);
        $transactions = array();

        foreach ($changes as $type => $value) {
          $transaction = clone $template;
          $transaction->setTransactionType($type);
          $transaction->setNewValue($value);
          $transactions[] = $transaction;
        }

        if ($aux_fields) {
          foreach ($aux_fields as $aux_field) {
            $transaction = clone $template;
            $transaction->setTransactionType(
              ManiphestTransactionType::TYPE_AUXILIARY);
            $aux_key = $aux_field->getAuxiliaryKey();
            $transaction->setMetadataValue('aux:key', $aux_key);
            $transaction->setNewValue($aux_field->getValueForStorage());
            $transactions[] = $transaction;
          }
        }

        if ($transactions) {
          $is_new = !$task->getID();

          $event = new PhabricatorEvent(
            PhabricatorEventType::TYPE_MANIPHEST_WILLEDITTASK,
            array(
              'task'          => $task,
              'new'           => $is_new,
              'transactions'  => $transactions,
            ));
          $event->setUser($user);
          $event->setAphrontRequest($request);
          PhutilEventEngine::dispatchEvent($event);

          $task = $event->getValue('task');
          $transactions = $event->getValue('transactions');

          $editor = new ManiphestTransactionEditor();
          $editor->setActor($user);
          $editor->setAuxiliaryFields($aux_fields);
          $editor->applyTransactions($task, $transactions);

          $event = new PhabricatorEvent(
            PhabricatorEventType::TYPE_MANIPHEST_DIDEDITTASK,
            array(
              'task'          => $task,
              'new'           => $is_new,
              'transactions'  => $transactions,
            ));
          $event->setUser($user);
          $event->setAphrontRequest($request);
          PhutilEventEngine::dispatchEvent($event);
        }


        if ($parent_task) {
          id(new PhabricatorEdgeEditor())
            ->setActor($user)
            ->addEdge(
              $parent_task->getPHID(),
              PhabricatorEdgeConfig::TYPE_TASK_DEPENDS_ON_TASK,
              $task->getPHID())
            ->save();
          $workflow = $parent_task->getID();
        }

        $redirect_uri = '/T'.$task->getID();

        if ($workflow) {
          $redirect_uri .= '?workflow='.$workflow;
        }

        return id(new AphrontRedirectResponse())
          ->setURI($redirect_uri);
      }
    } else {
      if (!$task->getID()) {
        $task->setCCPHIDs(array(
          $user->getPHID(),
        ));
        if ($template_id) {
          $template_task = id(new ManiphestTask())->load($template_id);
          if ($template_task) {
            $task->setCCPHIDs($template_task->getCCPHIDs());
            $task->setProjectPHIDs($template_task->getProjectPHIDs());
            $task->setOwnerPHID($template_task->getOwnerPHID());
            $task->setPriority($template_task->getPriority());

            if ($aux_fields) {
              $template_task->loadAndAttachAuxiliaryAttributes();
              foreach ($aux_fields as $aux_field) {
                if (!$aux_field->shouldCopyWhenCreatingSimilarTask()) {
                  continue;
                }

                $aux_key = $aux_field->getAuxiliaryKey();
                $value = $template_task->getAuxiliaryAttribute($aux_key);
                $aux_field->setValueFromStorage($value);
              }
            }
          }
        }
      }
    }

    $phids = array_merge(
      array($task->getOwnerPHID()),
      $task->getCCPHIDs(),
      $task->getProjectPHIDs(),
      array_mergev(mpull($aux_fields, 'getRequiredHandlePHIDs')));

    if ($parent_task) {
      $phids[] = $parent_task->getPHID();
    }

    $phids = array_filter($phids);
    $phids = array_unique($phids);

    $handles = $this->loadViewerHandles($phids);

    foreach ($aux_fields as $aux_field) {
      $aux_field->setHandles($handles);
    }

    $tvalues = mpull($handles, 'getFullName', 'getPHID');

    $error_view = null;
    if ($errors) {
      $error_view = new AphrontErrorView();
      $error_view->setErrors($errors);
      $error_view->setTitle(pht('Form Errors'));
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

    if ($task->getProjectPHIDs()) {
      $projects_value = array_select_keys($tvalues, $task->getProjectPHIDs());
    } else {
      $projects_value = array();
    }

    $cancel_id = nonempty($task->getID(), $template_id);
    if ($cancel_id) {
      $cancel_uri = '/T'.$cancel_id;
    } else {
      $cancel_uri = '/maniphest/';
    }

    if ($task->getID()) {
      $button_name = pht('Save Task');
      $header_name = pht('Edit Task');
    } else if ($parent_task) {
      $cancel_uri = '/T'.$parent_task->getID();
      $button_name = pht('Create Task');
      $header_name = pht('Create New Subtask');
    } else {
      $button_name = pht('Create Task');
      $header_name = pht('Create New Task');
    }

    require_celerity_resource('maniphest-task-edit-css');

    $project_tokenizer_id = celerity_generate_unique_node_id();

    $form = new AphrontFormView();
    $form
      ->setUser($user)
      ->setAction($request->getRequestURI()->getPath())
      ->addHiddenInput('template', $template_id);

    if ($parent_task) {
      $form
        ->appendChild(
          id(new AphrontFormStaticControl())
            ->setLabel(pht('Parent Task'))
            ->setValue($handles[$parent_task->getPHID()]->getFullName()))
        ->addHiddenInput('parent', $parent_task->getID());
    }

    $form
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setLabel(pht('Title'))
          ->setName('title')
          ->setError($e_title)
          ->setHeight(AphrontFormTextAreaControl::HEIGHT_VERY_SHORT)
          ->setValue($task->getTitle()));

    if ($task->getID()) {
      // Only show this in "edit" mode, not "create" mode, since creating a
      // non-open task is kind of silly and it would just clutter up the
      // "create" interface.
      $form
        ->appendChild(
          id(new AphrontFormSelectControl())
            ->setLabel(pht('Status'))
            ->setName('status')
            ->setValue($task->getStatus())
            ->setOptions(ManiphestTaskStatus::getTaskStatusMap()));
    }

    $form
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setLabel(pht('Assigned To'))
          ->setName('assigned_to')
          ->setValue($assigned_value)
          ->setUser($user)
          ->setDatasource('/typeahead/common/users/')
          ->setLimit(1))
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setLabel(pht('CC'))
          ->setName('cc')
          ->setValue($cc_value)
          ->setUser($user)
          ->setDatasource('/typeahead/common/mailable/'))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel(pht('Priority'))
          ->setName('priority')
          ->setOptions($priority_map)
          ->setValue($task->getPriority()))
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setLabel(pht('Projects'))
          ->setName('projects')
          ->setValue($projects_value)
          ->setID($project_tokenizer_id)
          ->setCaption(
            javelin_tag(
              'a',
              array(
                'href'        => '/project/create/',
                'mustcapture' => true,
                'sigil'       => 'project-create',
              ),
              pht('Create New Project')))
          ->setDatasource('/typeahead/common/projects/'));

    foreach ($aux_fields as $aux_field) {
      if ($aux_field->isRequired() &&
          !$aux_field->getError() &&
          !$aux_field->getValue()) {
        $aux_field->setError(true);
      }

      $aux_control = $aux_field->renderControl();
      $form->appendChild($aux_control);
    }

    require_celerity_resource('aphront-error-view-css');

    Javelin::initBehavior('project-create', array(
      'tokenizerID' => $project_tokenizer_id,
    ));

    if ($files) {
      $file_display = mpull($files, 'getName');
      $file_display = phutil_implode_html(phutil_tag('br'), $file_display);

      $form->appendChild(
        id(new AphrontFormMarkupControl())
          ->setLabel(pht('Files'))
          ->setValue($file_display));

      foreach ($files as $ii => $file) {
        $form->addHiddenInput('files['.$ii.']', $file->getPHID());
      }
    }


    $description_control = new PhabricatorRemarkupControl();
    // "Upsell" creating tasks via email in create flows if the instance is
    // configured for this awesomeness.
    $email_create = PhabricatorEnv::getEnvConfig(
      'metamta.maniphest.public-create-email');
    if (!$task->getID() && $email_create) {
      $email_hint = pht(
        'You can also create tasks by sending an email to: %s',
        phutil_tag('tt', array(), $email_create));
      $description_control->setCaption($email_hint);
    }

    $description_control
      ->setLabel(pht('Description'))
      ->setName('description')
      ->setID('description-textarea')
      ->setValue($task->getDescription())
      ->setUser($user);

    $form
      ->appendChild($description_control);

    if (!$task->getID()) {
      $form
        ->appendChild(
          id(new AphrontFormDragAndDropUploadControl())
            ->setLabel(pht('Attached Files'))
            ->setName('files')
            ->setActivatedClass('aphront-panel-view-drag-and-drop'));
    }

    $form
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->addCancelButton($cancel_uri)
          ->setValue($button_name));

    $panel = new AphrontPanelView();
    $panel->setWidth(AphrontPanelView::WIDTH_FULL);
    $panel->setHeader($header_name);
    $panel->appendChild($form);
    $panel->setNoBackground();
    $inst1 = pht('Description Preview');
    $inst2 = pht('Loading preview...');

    $description_preview_panel = hsprintf(
      '<div class="aphront-panel-preview aphront-panel-preview-full">
        <div class="maniphest-description-preview-header">
          %s
        </div>
        <div id="description-preview">
          <div class="aphront-panel-preview-loading-text">
            %s
          </div>
        </div>
      </div>',
      $inst1,
      $inst2);

    Javelin::initBehavior(
      'maniphest-description-preview',
      array(
        'preview'   => 'description-preview',
        'textarea'  => 'description-textarea',
        'uri'       => '/maniphest/task/descriptionpreview/',
      ));

    if ($task->getID()) {
      $page_objects = array( $task->getPHID() );
    } else {
      $page_objects = array();
    }

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName($header_name)
        ->setHref($this->getApplicationURI('/task/create/')))
      ->addAction(
        id(new PhabricatorMenuItemView())
          ->setHref($this->getApplicationURI('/task/create/'))
          ->setName(pht('Create Task'))
          ->setIcon('create'));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $error_view,
        $panel,
        $description_preview_panel,
      ),
      array(
        'title' => $header_name,
        'pageObjects' => $page_objects,
        'device' => true,
      ));
  }
}
