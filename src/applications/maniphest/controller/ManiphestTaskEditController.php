<?php

final class ManiphestTaskEditController extends ManiphestController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $id = $request->getURIData('id');

    $response_type = $request->getStr('responseType', 'task');
    $order = $request->getStr('order', PhabricatorProjectColumn::DEFAULT_ORDER);

    $can_edit_assign = $this->hasApplicationCapability(
      ManiphestEditAssignCapability::CAPABILITY);
    $can_edit_policies = $this->hasApplicationCapability(
      ManiphestEditPoliciesCapability::CAPABILITY);
    $can_edit_priority = $this->hasApplicationCapability(
      ManiphestEditPriorityCapability::CAPABILITY);
    $can_edit_projects = $this->hasApplicationCapability(
      ManiphestEditProjectsCapability::CAPABILITY);
    $can_edit_status = $this->hasApplicationCapability(
      ManiphestEditStatusCapability::CAPABILITY);
    $can_create_projects = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      PhabricatorApplication::getByClass('PhabricatorProjectApplication'),
      ProjectCreateProjectsCapability::CAPABILITY);

    $parent_task = null;
    $template_id = null;

    if ($id) {
      $task = id(new ManiphestTaskQuery())
        ->setViewer($viewer)
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_VIEW,
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
        ->withIDs(array($id))
        ->needSubscriberPHIDs(true)
        ->needProjectPHIDs(true)
        ->executeOne();
      if (!$task) {
        return new Aphront404Response();
      }
    } else {
      $task = ManiphestTask::initializeNewTask($viewer);

      // We currently do not allow you to set the task status when creating
      // a new task, although now that statuses are custom it might make
      // sense.
      $can_edit_status = false;

      // These allow task creation with defaults.
      if (!$request->isFormPost()) {
        $task->setTitle($request->getStr('title'));

        if ($can_edit_projects) {
          $projects = $request->getStr('projects');
          if ($projects) {
            $tokens = $request->getStrList('projects');

            $type_project = PhabricatorProjectProjectPHIDType::TYPECONST;
            foreach ($tokens as $key => $token) {
              if (phid_get_type($token) == $type_project) {
                // If this is formatted like a PHID, leave it as-is.
                continue;
              }

              if (preg_match('/^#/', $token)) {
                // If this already has a "#", leave it as-is.
                continue;
              }

              // Add a "#" prefix.
              $tokens[$key] = '#'.$token;
            }

            $default_projects = id(new PhabricatorObjectQuery())
              ->setViewer($viewer)
              ->withNames($tokens)
              ->execute();
            $default_projects = mpull($default_projects, 'getPHID');

            if ($default_projects) {
              $task->attachProjectPHIDs($default_projects);
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
              ->setViewer($viewer)
              ->withUsernames(array($assign))
              ->executeOne();
            if (!$assign_user) {
              $assign_user = id(new PhabricatorPeopleQuery())
                ->setViewer($viewer)
                ->withPHIDs(array($assign))
                ->executeOne();
            }

            if ($assign_user) {
              $task->setOwnerPHID($assign_user->getPHID());
            }
          }
        }
      }

      $template_id = $request->getInt('template');

      // You can only have a parent task if you're creating a new task.
      $parent_id = $request->getInt('parent');
      if (strlen($parent_id)) {
        $parent_task = id(new ManiphestTaskQuery())
          ->setViewer($viewer)
          ->withIDs(array($parent_id))
          ->executeOne();
        if (!$parent_task) {
          return new Aphront404Response();
        }
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
    $field_list->setViewer($viewer);
    $field_list->readFieldsFromStorage($task);

    $aux_fields = $field_list->getFields();

    $v_space = $task->getSpacePHID();

    if ($request->isFormPost()) {
      $changes = array();

      $new_title = $request->getStr('title');
      $new_desc = $request->getStr('description');
      $new_status = $request->getStr('status');
      $v_space = $request->getStr('spacePHID');

      if (!$task->getID()) {
        $workflow = 'create';
      } else {
        $workflow = '';
      }

      $changes[ManiphestTransaction::TYPE_TITLE] = $new_title;
      $changes[ManiphestTransaction::TYPE_DESCRIPTION] = $new_desc;

      if ($can_edit_status) {
        $changes[ManiphestTransaction::TYPE_STATUS] = $new_status;
      } else if (!$task->getID()) {
        // Create an initial status transaction for the burndown chart.
        // TODO: We can probably remove this once Facts comes online.
        $changes[ManiphestTransaction::TYPE_STATUS] = $task->getStatus();
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
        $placeholder_editor = id(new PhabricatorUserProfileEditor())
          ->setActor($viewer);

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
        $task->attachSubscriberPHIDs($request->getArr('cc'));
        $task->attachProjectPHIDs($request->getArr('projects'));
      } else {

        if ($can_edit_priority) {
          $changes[ManiphestTransaction::TYPE_PRIORITY] =
            $request->getInt('priority');
        }
        if ($can_edit_assign) {
          $changes[ManiphestTransaction::TYPE_OWNER] = $owner_phid;
        }

        $changes[PhabricatorTransactions::TYPE_SUBSCRIBERS] =
          array('=' => $request->getArr('cc'));

        if ($can_edit_projects) {
          $projects = $request->getArr('projects');
          $changes[PhabricatorTransactions::TYPE_EDGE] =
            $projects;
          $column_phid = $request->getStr('columnPHID');
          // allow for putting a task in a project column at creation -only-
          if (!$task->getID() && $column_phid && $projects) {
            $column = id(new PhabricatorProjectColumnQuery())
              ->setViewer($viewer)
              ->withProjectPHIDs($projects)
              ->withPHIDs(array($column_phid))
              ->executeOne();
            if ($column) {
              $changes[ManiphestTransaction::TYPE_PROJECT_COLUMN] =
                array(
                  'new' => array(
                    'projectPHID' => $column->getProjectPHID(),
                    'columnPHIDs' => array($column_phid),
                  ),
                  'old' => array(
                    'projectPHID' => $column->getProjectPHID(),
                    'columnPHIDs' => array(),
                  ),
                );
            }
          }
        }

        if ($can_edit_policies) {
          $changes[PhabricatorTransactions::TYPE_SPACE] = $v_space;
          $changes[PhabricatorTransactions::TYPE_VIEW_POLICY] =
            $request->getStr('viewPolicy');
          $changes[PhabricatorTransactions::TYPE_EDIT_POLICY] =
            $request->getStr('editPolicy');
        }

        $template = new ManiphestTransaction();
        $transactions = array();

        foreach ($changes as $type => $value) {
          $transaction = clone $template;
          $transaction->setTransactionType($type);
          if ($type == ManiphestTransaction::TYPE_PROJECT_COLUMN) {
            $transaction->setNewValue($value['new']);
            $transaction->setOldValue($value['old']);
          } else if ($type == PhabricatorTransactions::TYPE_EDGE) {
            $project_type =
              PhabricatorProjectObjectHasProjectEdgeType::EDGECONST;
            $transaction
              ->setMetadataValue('edge:type', $project_type)
              ->setNewValue(
                array(
                  '=' => array_fuse($value),
                ));
          } else {
            $transaction->setNewValue($value);
          }
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
          $event->setUser($viewer);
          $event->setAphrontRequest($request);
          PhutilEventEngine::dispatchEvent($event);

          $task = $event->getValue('task');
          $transactions = $event->getValue('transactions');

          $editor = id(new ManiphestTransactionEditor())
            ->setActor($viewer)
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
          $event->setUser($viewer);
          $event->setAphrontRequest($request);
          PhutilEventEngine::dispatchEvent($event);
        }


        if ($parent_task) {
          // TODO: This should be transactional now.
          id(new PhabricatorEdgeEditor())
            ->addEdge(
              $parent_task->getPHID(),
              ManiphestTaskDependsOnTaskEdgeType::EDGECONST,
              $task->getPHID())
            ->save();
          $workflow = $parent_task->getID();
        }

        if ($request->isAjax()) {
          switch ($response_type) {
            case 'card':
              $owner = null;
              if ($task->getOwnerPHID()) {
                $owner = id(new PhabricatorHandleQuery())
                  ->setViewer($viewer)
                  ->withPHIDs(array($task->getOwnerPHID()))
                  ->executeOne();
              }
              $tasks = id(new ProjectBoardTaskCard())
                ->setViewer($viewer)
                ->setTask($task)
                ->setOwner($owner)
                ->setCanEdit(true)
                ->getItem();

              $column = id(new PhabricatorProjectColumnQuery())
                ->setViewer($viewer)
                ->withPHIDs(array($request->getStr('columnPHID')))
                ->executeOne();
              if (!$column) {
                return new Aphront404Response();
              }

              // re-load projects for accuracy as they are not re-loaded via
              // the editor
              $project_phids = PhabricatorEdgeQuery::loadDestinationPHIDs(
                $task->getPHID(),
                PhabricatorProjectObjectHasProjectEdgeType::EDGECONST);
              $task->attachProjectPHIDs($project_phids);
              $remove_from_board = false;
              if (!in_array($column->getProjectPHID(), $project_phids)) {
                $remove_from_board = true;
              }

              $positions = id(new PhabricatorProjectColumnPositionQuery())
                ->setViewer($viewer)
                ->withColumns(array($column))
                ->execute();
              $task_phids = mpull($positions, 'getObjectPHID');

              $column_tasks = id(new ManiphestTaskQuery())
                ->setViewer($viewer)
                ->withPHIDs($task_phids)
                ->execute();

              if ($order == PhabricatorProjectColumn::ORDER_NATURAL) {
                // TODO: This is a little bit awkward, because PHP and JS use
                // slightly different sort order parameters to achieve the same
                // effect. It would be good to unify this a bit at some point.
                $sort_map = array();
                foreach ($positions as $position) {
                  $sort_map[$position->getObjectPHID()] = array(
                    -$position->getSequence(),
                    $position->getID(),
                  );
                }
              } else {
                $sort_map = mpull(
                  $column_tasks,
                  'getPrioritySortVector',
                  'getPHID');
              }

              $data = array(
                'sortMap' => $sort_map,
                'removeFromBoard' => $remove_from_board,
              );
              break;
            case 'task':
            default:
              $tasks = $this->renderSingleTask($task);
              $data = array();
              break;
          }
          return id(new AphrontAjaxResponse())->setContent(
            array(
              'tasks' => $tasks,
              'data' => $data,
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
        $task->attachSubscriberPHIDs(array(
          $viewer->getPHID(),
        ));
        if ($template_id) {
          $template_task = id(new ManiphestTaskQuery())
            ->setViewer($viewer)
            ->withIDs(array($template_id))
            ->needSubscriberPHIDs(true)
            ->needProjectPHIDs(true)
            ->executeOne();
          if ($template_task) {
            $cc_phids = array_unique(array_merge(
              $template_task->getSubscriberPHIDs(),
              array($viewer->getPHID())));
            $task->attachSubscriberPHIDs($cc_phids);
            $task->attachProjectPHIDs($template_task->getProjectPHIDs());
            $task->setOwnerPHID($template_task->getOwnerPHID());
            $task->setPriority($template_task->getPriority());
            $task->setViewPolicy($template_task->getViewPolicy());
            $task->setEditPolicy($template_task->getEditPolicy());

            $v_space = $template_task->getSpacePHID();

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
                ->setViewer($viewer)
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

    $error_view = null;
    if ($errors) {
      $error_view = new PHUIInfoView();
      $error_view->setErrors($errors);
    }

    $priority_map = ManiphestTaskPriority::getTaskPriorityMap();

    if ($task->getOwnerPHID()) {
      $assigned_value = array($task->getOwnerPHID());
    } else {
      $assigned_value = array();
    }

    if ($task->getSubscriberPHIDs()) {
      $cc_value = $task->getSubscriberPHIDs();
    } else {
      $cc_value = array();
    }

    if ($task->getProjectPHIDs()) {
      $projects_value = $task->getProjectPHIDs();
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
      ->setUser($viewer)
      ->addHiddenInput('template', $template_id)
      ->addHiddenInput('responseType', $response_type)
      ->addHiddenInput('order', $order)
      ->addHiddenInput('ungrippable', $request->getStr('ungrippable'))
      ->addHiddenInput('columnPHID', $request->getStr('columnPHID'));

    if ($parent_task) {
      $form
        ->appendChild(
          id(new AphrontFormStaticControl())
            ->setLabel(pht('Parent Task'))
            ->setValue($viewer->renderHandle($parent_task->getPHID())))
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

    if ($can_edit_status) {
      // See T4819.
      $status_map = ManiphestTaskStatus::getTaskStatusMap();
      $dup_status = ManiphestTaskStatus::getDuplicateStatus();

      if ($task->getStatus() != $dup_status) {
        unset($status_map[$dup_status]);
      }

      $form
        ->appendChild(
          id(new AphrontFormSelectControl())
            ->setLabel(pht('Status'))
            ->setName('status')
            ->setValue($task->getStatus())
            ->setOptions($status_map));
    }

    $policies = id(new PhabricatorPolicyQuery())
      ->setViewer($viewer)
      ->setObject($task)
      ->execute();

    if ($can_edit_assign) {
      $form->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setLabel(pht('Assigned To'))
          ->setName('assigned_to')
          ->setValue($assigned_value)
          ->setUser($viewer)
          ->setDatasource(new PhabricatorPeopleDatasource())
          ->setLimit(1));
    }

    $form
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setLabel(pht('CC'))
          ->setName('cc')
          ->setValue($cc_value)
          ->setUser($viewer)
          ->setDatasource(new PhabricatorMetaMTAMailableDatasource()));

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
            ->setUser($viewer)
            ->setCapability(PhabricatorPolicyCapability::CAN_VIEW)
            ->setPolicyObject($task)
            ->setPolicies($policies)
            ->setSpacePHID($v_space)
            ->setName('viewPolicy'))
        ->appendChild(
          id(new AphrontFormPolicyControl())
            ->setUser($viewer)
            ->setCapability(PhabricatorPolicyCapability::CAN_EDIT)
            ->setPolicyObject($task)
            ->setPolicies($policies)
            ->setName('editPolicy'));
    }

    if ($can_edit_projects) {
      $caption = null;
      if ($can_create_projects) {
        $caption = javelin_tag(
          'a',
          array(
            'href'        => '/project/create/',
            'mustcapture' => true,
            'sigil'       => 'project-create',
          ),
          pht('Create New Project'));
      }
      $form
        ->appendControl(
          id(new AphrontFormTokenizerControl())
            ->setLabel(pht('Projects'))
            ->setName('projects')
            ->setValue($projects_value)
            ->setID($project_tokenizer_id)
            ->setCaption($caption)
            ->setDatasource(new PhabricatorProjectDatasource()));
    }

    $field_list->appendFieldsToForm($form);

    require_celerity_resource('phui-info-view-css');

    Javelin::initBehavior('project-create', array(
      'tokenizerID' => $project_tokenizer_id,
    ));

    $description_control = id(new PhabricatorRemarkupControl())
      ->setLabel(pht('Description'))
      ->setName('description')
      ->setID('description-textarea')
      ->setValue($task->getDescription())
      ->setUser($viewer);

    $form
      ->appendChild($description_control);

    if ($request->isAjax()) {
      $dialog = id(new AphrontDialogView())
        ->setUser($viewer)
        ->setWidth(AphrontDialogView::WIDTH_FULL)
        ->setTitle($header_name)
        ->appendChild(
          array(
            $error_view,
            $form->buildLayoutView(),
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
      ->setFormErrors($errors)
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
      $crumbs->addTextCrumb('T'.$task->getID(), '/T'.$task->getID());
    }

    $crumbs->addTextCrumb($header_name);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $form_box,
        $preview,
      ),
      array(
        'title' => $header_name,
        'pageObjects' => $page_objects,
      ));
  }

}
