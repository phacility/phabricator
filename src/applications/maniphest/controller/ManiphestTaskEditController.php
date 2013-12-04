<?php

final class ManiphestTaskEditController extends ManiphestController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = idx($data, 'id');
  }

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    $can_edit_assign = $this->hasApplicationCapability(
      ManiphestCapabilityEditAssign::CAPABILITY);
    $can_edit_policies = $this->hasApplicationCapability(
      ManiphestCapabilityEditPolicies::CAPABILITY);
    $can_edit_priority = $this->hasApplicationCapability(
      ManiphestCapabilityEditPriority::CAPABILITY);
    $can_edit_projects = $this->hasApplicationCapability(
      ManiphestCapabilityEditProjects::CAPABILITY);
    $can_edit_status = $this->hasApplicationCapability(
      ManiphestCapabilityEditStatus::CAPABILITY);

    $files = array();
    $parent_task = null;
    $template_id = null;

    if ($this->id) {
      $task = id(new ManiphestTaskQuery())
        ->setViewer($user)
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_VIEW,
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
        ->withIDs(array($this->id))
        ->executeOne();
      if (!$task) {
        return new Aphront404Response();
      }
    } else {
      $task = ManiphestTask::initializeNewTask($user);

      // These allow task creation with defaults.
      if (!$request->isFormPost()) {
        $task->setTitle($request->getStr('title'));

        if ($can_edit_projects) {
          $projects = $request->getStr('projects');
          if ($projects) {
            $tokens = explode(';', $projects);

            $slug_map = id(new PhabricatorProjectQuery())
              ->setViewer($user)
              ->withPhrictionSlugs($tokens)
              ->execute();

            $name_map = id(new PhabricatorProjectQuery())
              ->setViewer($user)
              ->withNames($tokens)
              ->execute();

            $phid_map = id(new PhabricatorProjectQuery())
              ->setViewer($user)
              ->withPHIDs($tokens)
              ->execute();

            $all_map = mpull($slug_map, null, 'getPhrictionSlug') +
              mpull($name_map, null, 'getName') +
              mpull($phid_map, null, 'getPHID');

            $default_projects = array();
            foreach ($tokens as $token) {
              if (isset($all_map[$token])) {
                $default_projects[] = $all_map[$token]->getPHID();
              }
            }

            if ($default_projects) {
              $task->setProjectPHIDs($default_projects);
            }
          }
        }

        if ($can_edit_priority) {
          $priority = $request->getInt('priority');
          if ($priority !== null) {
            $priority_map = ManiphestTaskPriority::getTaskPriorityMap();
            if (isset($priority_map[$priority])) {
                $task->setPriority($priority);
            }
          }
        }

        $task->setDescription($request->getStr('description'));

        if ($can_edit_assign) {
          $assign = $request->getStr('assign');
          if (strlen($assign)) {
            $assign_user = id(new PhabricatorPeopleQuery())
              ->setViewer($user)
              ->withUsernames(array($assign))
              ->executeOne();
            if (!$assign_user) {
              $assign_user = id(new PhabricatorPeopleQuery())
                ->setViewer($user)
                ->withPHIDs(array($assign))
                ->executeOne();
            }

            if ($assign_user) {
              $task->setOwnerPHID($assign_user->getPHID());
            }
          }
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
        $files = id(new PhabricatorFileQuery())
          ->setViewer($user)
          ->withPHIDs($file_phids)
          ->execute();
      }

      $template_id = $request->getInt('template');

      // You can only have a parent task if you're creating a new task.
      $parent_id = $request->getInt('parent');
      if ($parent_id) {
        $parent_task = id(new ManiphestTaskQuery())
          ->setViewer($user)
          ->withIDs(array($parent_id))
          ->executeOne();
        if (!$template_id) {
          $template_id = $parent_id;
        }
      }
    }

    $errors = array();
    $e_title = true;

    $field_list = PhabricatorCustomField::getObjectFields(
      $task,
      PhabricatorCustomField::ROLE_EDIT);

    foreach ($field_list->getFields() as $field) {
      $field->setObject($task);
      $field->setViewer($user);
    }

    $field_list->readFieldsFromStorage($task);

    $aux_fields = $field_list->getFields();

    if ($request->isFormPost()) {
      $changes = array();

      $new_title = $request->getStr('title');
      $new_desc = $request->getStr('description');
      $new_status = $request->getStr('status');

      if (!$task->getID()) {
        $workflow = 'create';
      } else {
        $workflow = '';
      }

      $changes[ManiphestTransaction::TYPE_TITLE] = $new_title;
      $changes[ManiphestTransaction::TYPE_DESCRIPTION] = $new_desc;
      if ($can_edit_status) {
        $changes[ManiphestTransaction::TYPE_STATUS] = $new_status;
      }

      $owner_tokenizer = $request->getArr('assigned_to');
      $owner_phid = reset($owner_tokenizer);

      if (!strlen($new_title)) {
        $e_title = pht('Required');
        $errors[] = pht('Title is required.');
      }

      $old_values = array();
      foreach ($aux_fields as $aux_arr_key => $aux_field) {
        // TODO: This should be buildFieldTransactionsFromRequest() once we
        // switch to ApplicationTransactions properly.

        $aux_old_value = $aux_field->getOldValueForApplicationTransactions();
        $aux_field->readValueFromRequest($request);
        $aux_new_value = $aux_field->getNewValueForApplicationTransactions();

        // TODO: We're faking a call to the ApplicaitonTransaction validation
        // logic here. We need valid objects to pass, but they aren't used
        // in a meaningful way. For now, build User objects. Once the Maniphest
        // objects exist, this will switch over automatically. This is a big
        // hack but shouldn't be long for this world.
        $placeholder_editor = new PhabricatorUserProfileEditor();

        $field_errors = $aux_field->validateApplicationTransactions(
          $placeholder_editor,
          PhabricatorTransactions::TYPE_CUSTOMFIELD,
          array(
            id(new ManiphestTransaction())
              ->setOldValue($aux_old_value)
              ->setNewValue($aux_new_value),
          ));

        foreach ($field_errors as $error) {
          $errors[] = $error->getMessage();
        }

        $old_values[$aux_field->getFieldKey()] = $aux_old_value;
      }

      if ($errors) {
        $task->setTitle($new_title);
        $task->setDescription($new_desc);
        $task->setPriority($request->getInt('priority'));
        $task->setOwnerPHID($owner_phid);
        $task->setCCPHIDs($request->getArr('cc'));
        $task->setProjectPHIDs($request->getArr('projects'));
      } else {

        if ($can_edit_priority) {
          $changes[ManiphestTransaction::TYPE_PRIORITY] =
            $request->getInt('priority');
        }
        if ($can_edit_assign) {
          $changes[ManiphestTransaction::TYPE_OWNER] = $owner_phid;
        }

        $changes[ManiphestTransaction::TYPE_CCS] = $request->getArr('cc');

        if ($can_edit_projects) {
          $changes[ManiphestTransaction::TYPE_PROJECTS] =
            $request->getArr('projects');
        }

        if ($can_edit_policies) {
          $changes[PhabricatorTransactions::TYPE_VIEW_POLICY] =
            $request->getStr('viewPolicy');
          $changes[PhabricatorTransactions::TYPE_EDIT_POLICY] =
            $request->getStr('editPolicy');
        }

        if ($files) {
          $file_map = mpull($files, 'getPHID');
          $file_map = array_fill_keys($file_map, array());
          $changes[ManiphestTransaction::TYPE_ATTACH] = array(
            PhabricatorFilePHIDTypeFile::TYPECONST => $file_map,
          );
        }

        $template = new ManiphestTransaction();
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
              PhabricatorTransactions::TYPE_CUSTOMFIELD);
            $aux_key = $aux_field->getFieldKey();
            $transaction->setMetadataValue('customfield:key', $aux_key);
            $old = idx($old_values, $aux_key);
            $new = $aux_field->getNewValueForApplicationTransactions();

            $transaction->setOldValue($old);
            $transaction->setNewValue($new);

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

          $editor = id(new ManiphestTransactionEditor())
            ->setActor($user)
            ->setContentSourceFromRequest($request)
            ->setContinueOnNoEffect(true)
            ->applyTransactions($task, $transactions);

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

        if ($request->isAjax()) {
          return id(new AphrontAjaxResponse())->setContent(
            array(
              'tasks' => $this->renderSingleTask($task),
            ));
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
          $template_task = id(new ManiphestTaskQuery())
            ->setViewer($user)
            ->withIDs(array($template_id))
            ->executeOne();
          if ($template_task) {
            $task->setCCPHIDs($template_task->getCCPHIDs());
            $task->setProjectPHIDs($template_task->getProjectPHIDs());
            $task->setOwnerPHID($template_task->getOwnerPHID());
            $task->setPriority($template_task->getPriority());
            $task->setViewPolicy($template_task->getViewPolicy());
            $task->setEditPolicy($template_task->getEditPolicy());

            $template_fields = PhabricatorCustomField::getObjectFields(
              $template_task,
              PhabricatorCustomField::ROLE_EDIT);

            $fields = $template_fields->getFields();
            foreach ($fields as $key => $field) {
              if (!$field->shouldCopyWhenCreatingSimilarTask()) {
                unset($fields[$key]);
              }
              if (empty($aux_fields[$key])) {
                unset($fields[$key]);
              }
            }

            if ($fields) {
              id(new PhabricatorCustomFieldList($fields))
                ->readFieldsFromStorage($template_task);

              foreach ($fields as $key => $field) {
                $aux_fields[$key]->setValueFromStorage(
                  $field->getValueForStorage());
              }
            }
          }
        }
      }
    }

    $phids = array_merge(
      array($task->getOwnerPHID()),
      $task->getCCPHIDs(),
      $task->getProjectPHIDs());

    if ($parent_task) {
      $phids[] = $parent_task->getPHID();
    }

    $phids = array_filter($phids);
    $phids = array_unique($phids);

    $handles = $this->loadViewerHandles($phids);

    $error_view = null;
    if ($errors) {
      $error_view = new AphrontErrorView();
      $error_view->setErrors($errors);
      $error_view->setTitle(pht('Form Errors'));
    }

    $priority_map = ManiphestTaskPriority::getTaskPriorityMap();

    if ($task->getOwnerPHID()) {
      $assigned_value = array($handles[$task->getOwnerPHID()]);
    } else {
      $assigned_value = array();
    }

    if ($task->getCCPHIDs()) {
      $cc_value = array_select_keys($handles, $task->getCCPHIDs());
    } else {
      $cc_value = array();
    }

    if ($task->getProjectPHIDs()) {
      $projects_value = array_select_keys($handles, $task->getProjectPHIDs());
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

    if ($request->isAjax()) {
      $form = new PHUIFormLayoutView();
    } else {
      $form = new AphrontFormView();
      $form
        ->setUser($user)
        ->addHiddenInput('template', $template_id);
    }

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

    if ($task->getID() && $can_edit_status) {
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

    $policies = id(new PhabricatorPolicyQuery())
      ->setViewer($user)
      ->setObject($task)
      ->execute();

    if ($can_edit_assign) {
      $form->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setLabel(pht('Assigned To'))
          ->setName('assigned_to')
          ->setValue($assigned_value)
          ->setUser($user)
          ->setDatasource('/typeahead/common/users/')
          ->setLimit(1));
    }

    $form
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setLabel(pht('CC'))
          ->setName('cc')
          ->setValue($cc_value)
          ->setUser($user)
          ->setDatasource('/typeahead/common/mailable/'));

    if ($can_edit_priority) {
      $form
        ->appendChild(
          id(new AphrontFormSelectControl())
            ->setLabel(pht('Priority'))
            ->setName('priority')
            ->setOptions($priority_map)
            ->setValue($task->getPriority()));
    }

    if ($can_edit_policies) {
      $form
        ->appendChild(
          id(new AphrontFormPolicyControl())
            ->setUser($user)
            ->setCapability(PhabricatorPolicyCapability::CAN_VIEW)
            ->setPolicyObject($task)
            ->setPolicies($policies)
            ->setName('viewPolicy'))
        ->appendChild(
          id(new AphrontFormPolicyControl())
            ->setUser($user)
            ->setCapability(PhabricatorPolicyCapability::CAN_EDIT)
            ->setPolicyObject($task)
            ->setPolicies($policies)
            ->setName('editPolicy'));
    }

    if ($can_edit_projects) {
      $form
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
    }

    foreach ($aux_fields as $aux_field) {
      $aux_control = $aux_field->renderEditControl();
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


    if ($request->isAjax()) {
      $dialog = id(new AphrontDialogView())
        ->setUser($user)
        ->setWidth(AphrontDialogView::WIDTH_FULL)
        ->setTitle($header_name)
        ->appendChild(
          array(
            $error_view,
            $form,
          ))
        ->addCancelButton($cancel_uri)
        ->addSubmitButton($button_name);
      return id(new AphrontDialogResponse())->setDialog($dialog);
    }

    $form
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->addCancelButton($cancel_uri)
          ->setValue($button_name));

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText($header_name)
      ->setFormError($error_view)
      ->setForm($form);

    $preview = id(new PHUIRemarkupPreviewPanel())
      ->setHeader(pht('Description Preview'))
      ->setControlID('description-textarea')
      ->setPreviewURI($this->getApplicationURI('task/descriptionpreview/'));

    if ($task->getID()) {
      $page_objects = array($task->getPHID());
    } else {
      $page_objects = array();
    }

    $crumbs = $this->buildApplicationCrumbs();

    if ($task->getID()) {
      $crumbs->addCrumb(
        id(new PhabricatorCrumbView())
          ->setName('T'.$task->getID())
          ->setHref('/T'.$task->getID()));
    }

    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName($header_name));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $form_box,
        $preview,
      ),
      array(
        'title' => $header_name,
        'pageObjects' => $page_objects,
        'device' => true,
      ));
  }
}
