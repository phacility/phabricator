<?php

final class ManiphestBatchEditController extends ManiphestController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $this->requireApplicationCapability(
      ManiphestBulkEditCapability::CAPABILITY);

    $project = null;
    $board_id = $request->getInt('board');
    if ($board_id) {
      $project = id(new PhabricatorProjectQuery())
        ->setViewer($viewer)
        ->withIDs(array($board_id))
        ->executeOne();
      if (!$project) {
        return new Aphront404Response();
      }
    }

    $task_ids = $request->getArr('batch');
    if (!$task_ids) {
      $task_ids = $request->getStrList('batch');
    }

    $tasks = id(new ManiphestTaskQuery())
      ->setViewer($viewer)
      ->withIDs($task_ids)
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->needSubscriberPHIDs(true)
      ->needProjectPHIDs(true)
      ->execute();

    if ($project) {
      $cancel_uri = '/project/board/'.$project->getID().'/';
      $redirect_uri = $cancel_uri;
    } else {
      $cancel_uri = '/maniphest/';
      $redirect_uri = '/maniphest/?ids='.implode(',', mpull($tasks, 'getID'));
    }

    $actions = $request->getStr('actions');
    if ($actions) {
      $actions = phutil_json_decode($actions);
    }

    if ($request->isFormPost() && is_array($actions)) {
      foreach ($tasks as $task) {
        $field_list = PhabricatorCustomField::getObjectFields(
          $task,
          PhabricatorCustomField::ROLE_EDIT);
        $field_list->readFieldsFromStorage($task);

        $xactions = $this->buildTransactions($actions, $task);
        if ($xactions) {
          // TODO: Set content source to "batch edit".

          $editor = id(new ManiphestTransactionEditor())
            ->setActor($viewer)
            ->setContentSourceFromRequest($request)
            ->setContinueOnNoEffect(true)
            ->setContinueOnMissingFields(true)
            ->applyTransactions($task, $xactions);
        }
      }

      return id(new AphrontRedirectResponse())->setURI($redirect_uri);
    }

    $handles = ManiphestTaskListView::loadTaskHandles($viewer, $tasks);

    $list = new ManiphestTaskListView();
    $list->setTasks($tasks);
    $list->setUser($viewer);
    $list->setHandles($handles);

    $template = new AphrontTokenizerTemplateView();
    $template = $template->render();

    $projects_source = new PhabricatorProjectDatasource();
    $mailable_source = new PhabricatorMetaMTAMailableDatasource();
    $mailable_source->setViewer($viewer);
    $owner_source = new ManiphestAssigneeDatasource();
    $owner_source->setViewer($viewer);

    require_celerity_resource('maniphest-batch-editor');
    Javelin::initBehavior(
      'maniphest-batch-editor',
      array(
        'root' => 'maniphest-batch-edit-form',
        'tokenizerTemplate' => $template,
        'sources' => array(
          'project' => array(
            'src' => $projects_source->getDatasourceURI(),
            'placeholder' => $projects_source->getPlaceholderText(),
            'browseURI' => $projects_source->getBrowseURI(),
          ),
          'owner' => array(
            'src' => $owner_source->getDatasourceURI(),
            'placeholder' => $owner_source->getPlaceholderText(),
            'browseURI' => $owner_source->getBrowseURI(),
            'limit' => 1,
          ),
          'cc' => array(
            'src' => $mailable_source->getDatasourceURI(),
            'placeholder' => $mailable_source->getPlaceholderText(),
            'browseURI' => $mailable_source->getBrowseURI(),
          ),
        ),
        'input' => 'batch-form-actions',
        'priorityMap' => ManiphestTaskPriority::getTaskPriorityMap(),
        'statusMap'   => ManiphestTaskStatus::getTaskStatusMap(),
      ));

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->addHiddenInput('board', $board_id)
      ->setID('maniphest-batch-edit-form');

    foreach ($tasks as $task) {
      $form->appendChild(
        phutil_tag(
          'input',
          array(
            'type' => 'hidden',
            'name' => 'batch[]',
            'value' => $task->getID(),
          )));
    }

    $form->appendChild(
      phutil_tag(
        'input',
        array(
          'type' => 'hidden',
          'name' => 'actions',
          'id'   => 'batch-form-actions',
        )));
    $form->appendChild(
      id(new PHUIFormInsetView())
        ->setTitle(pht('Actions'))
        ->setRightButton(javelin_tag(
            'a',
            array(
              'href' => '#',
              'class' => 'button green',
              'sigil' => 'add-action',
              'mustcapture' => true,
            ),
            pht('Add Another Action')))
        ->setContent(javelin_tag(
          'table',
          array(
            'sigil' => 'maniphest-batch-actions',
            'class' => 'maniphest-batch-actions-table',
          ),
          '')))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Update Tasks'))
          ->addCancelButton($cancel_uri));

    $title = pht('Batch Editor');

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($title);

    $task_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Selected Tasks'))
      ->appendChild($list);

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Batch Editor'))
      ->setForm($form);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $task_box,
        $form_box,
      ),
      array(
        'title' => $title,
      ));
  }

  private function buildTransactions($actions, ManiphestTask $task) {
    $value_map = array();
    $type_map = array(
      'add_comment'     => PhabricatorTransactions::TYPE_COMMENT,
      'assign'          => ManiphestTransaction::TYPE_OWNER,
      'status'          => ManiphestTransaction::TYPE_STATUS,
      'priority'        => ManiphestTransaction::TYPE_PRIORITY,
      'add_project'     => PhabricatorTransactions::TYPE_EDGE,
      'remove_project'  => PhabricatorTransactions::TYPE_EDGE,
      'add_ccs'         => PhabricatorTransactions::TYPE_SUBSCRIBERS,
      'remove_ccs'      => PhabricatorTransactions::TYPE_SUBSCRIBERS,
    );

    $edge_edit_types = array(
      'add_project'    => true,
      'remove_project' => true,
      'add_ccs'        => true,
      'remove_ccs'     => true,
    );

    $xactions = array();
    foreach ($actions as $action) {
      if (empty($type_map[$action['action']])) {
        throw new Exception(pht("Unknown batch edit action '%s'!", $action));
      }

      $type = $type_map[$action['action']];

      // Figure out the current value, possibly after modifications by other
      // batch actions of the same type. For example, if the user chooses to
      // "Add Comment" twice, we should add both comments. More notably, if the
      // user chooses "Remove Project..." and also "Add Project...", we should
      // avoid restoring the removed project in the second transaction.

      if (array_key_exists($type, $value_map)) {
        $current = $value_map[$type];
      } else {
        switch ($type) {
          case PhabricatorTransactions::TYPE_COMMENT:
            $current = null;
            break;
          case ManiphestTransaction::TYPE_OWNER:
            $current = $task->getOwnerPHID();
            break;
          case ManiphestTransaction::TYPE_STATUS:
            $current = $task->getStatus();
            break;
          case ManiphestTransaction::TYPE_PRIORITY:
            $current = $task->getPriority();
            break;
          case PhabricatorTransactions::TYPE_EDGE:
            $current = $task->getProjectPHIDs();
            break;
          case PhabricatorTransactions::TYPE_SUBSCRIBERS:
            $current = $task->getSubscriberPHIDs();
            break;
        }
      }

      // Check if the value is meaningful / provided, and normalize it if
      // necessary. This discards, e.g., empty comments and empty owner
      // changes.

      $value = $action['value'];
      switch ($type) {
        case PhabricatorTransactions::TYPE_COMMENT:
          if (!strlen($value)) {
            continue 2;
          }
          break;
        case ManiphestTransaction::TYPE_OWNER:
          if (empty($value)) {
            continue 2;
          }
          $value = head($value);
          $no_owner = PhabricatorPeopleNoOwnerDatasource::FUNCTION_TOKEN;
          if ($value === $no_owner) {
            $value = null;
          }
          break;
        case PhabricatorTransactions::TYPE_EDGE:
          if (empty($value)) {
            continue 2;
          }
          break;
        case PhabricatorTransactions::TYPE_SUBSCRIBERS:
          if (empty($value)) {
            continue 2;
          }
          break;
      }

      // If the edit doesn't change anything, go to the next action. This
      // check is only valid for changes like "owner", "status", etc, not
      // for edge edits, because we should still apply an edit like
      // "Remove Projects: A, B" to a task with projects "A, B".

      if (empty($edge_edit_types[$action['action']])) {
        if ($value == $current) {
          continue;
        }
      }

      // Apply the value change; for most edits this is just replacement, but
      // some need to merge the current and edited values (add/remove project).

      switch ($type) {
        case PhabricatorTransactions::TYPE_COMMENT:
          if (strlen($current)) {
            $value = $current."\n\n".$value;
          }
          break;
        case PhabricatorTransactions::TYPE_EDGE:
          $is_remove = $action['action'] == 'remove_project';

          $current = array_fill_keys($current, true);
          $value   = array_fill_keys($value, true);

          $new = $current;
          $did_something = false;

          if ($is_remove) {
            foreach ($value as $phid => $ignored) {
              if (isset($new[$phid])) {
                unset($new[$phid]);
                $did_something = true;
              }
            }
          } else {
            foreach ($value as $phid => $ignored) {
              if (empty($new[$phid])) {
                $new[$phid] = true;
                $did_something = true;
              }
            }
          }

          if (!$did_something) {
            continue 2;
          }

          $value = array_keys($new);
          break;
        case PhabricatorTransactions::TYPE_SUBSCRIBERS:
          $is_remove = $action['action'] == 'remove_ccs';

          $current = array_fill_keys($current, true);

          $new = array();
          $did_something = false;

          if ($is_remove) {
            foreach ($value as $phid) {
              if (isset($current[$phid])) {
                $new[$phid] = true;
                $did_something = true;
              }
            }
            if ($new) {
              $value = array('-' => array_keys($new));
            }
          } else {
            $new = array();
            foreach ($value as $phid) {
              $new[$phid] = true;
              $did_something = true;
            }
            if ($new) {
              $value = array('+' => array_keys($new));
            }
          }
          if (!$did_something) {
            continue 2;
          }

          break;
      }

      $value_map[$type] = $value;
    }

    $template = new ManiphestTransaction();

    foreach ($value_map as $type => $value) {
      $xaction = clone $template;
      $xaction->setTransactionType($type);

      switch ($type) {
        case PhabricatorTransactions::TYPE_COMMENT:
          $xaction->attachComment(
            id(new ManiphestTransactionComment())
              ->setContent($value));
          break;
        case PhabricatorTransactions::TYPE_EDGE:
          $project_type = PhabricatorProjectObjectHasProjectEdgeType::EDGECONST;
          $xaction
            ->setMetadataValue('edge:type', $project_type)
            ->setNewValue(
              array(
                '=' => array_fuse($value),
              ));
          break;
        default:
          $xaction->setNewValue($value);
          break;
      }

      $xactions[] = $xaction;
    }

    return $xactions;
  }

}
