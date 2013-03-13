<?php

/**
 * @group maniphest
 */
final class ManiphestBatchEditController extends ManiphestController {

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    $task_ids = $request->getArr('batch');
    $tasks = id(new ManiphestTask())->loadAllWhere(
      'id IN (%Ld)',
      $task_ids);

    $actions = $request->getStr('actions');
    if ($actions) {
      $actions = json_decode($actions, true);
    }

    if ($request->isFormPost() && is_array($actions)) {
      foreach ($tasks as $task) {
        $xactions = $this->buildTransactions($actions, $task);
        if ($xactions) {
          $editor = new ManiphestTransactionEditor();
          $editor->setActor($user);
          $editor->applyTransactions($task, $xactions);
        }
      }

      $task_ids = implode(',', mpull($tasks, 'getID'));

      return id(new AphrontRedirectResponse())
        ->setURI('/maniphest/view/custom/?s=oc&tasks='.$task_ids);
    }

    $panel = new AphrontPanelView();
    $panel->setHeader(pht('Maniphest Batch Editor'));
    $panel->setNoBackground();

    $handle_phids = mpull($tasks, 'getOwnerPHID');
    $handles = $this->loadViewerHandles($handle_phids);

    $list = new ManiphestTaskListView();
    $list->setTasks($tasks);
    $list->setUser($user);
    $list->setHandles($handles);

    $template = new AphrontTokenizerTemplateView();
    $template = $template->render();

    require_celerity_resource('maniphest-batch-editor');
    Javelin::initBehavior(
      'maniphest-batch-editor',
      array(
        'root' => 'maniphest-batch-edit-form',
        'tokenizerTemplate' => $template,
        'sources' => array(
          'project' => array(
            'src'           => '/typeahead/common/projects/',
            'placeholder'   => pht('Type a project name...'),
          ),
          'owner' => array(
            'src'           => '/typeahead/common/searchowner/',
            'placeholder'   => pht('Type a user name...'),
            'limit'         => 1,
          ),
        ),
        'input' => 'batch-form-actions',
        'priorityMap' => ManiphestTaskPriority::getTaskPriorityMap(),
        'statusMap'   => ManiphestTaskStatus::getTaskStatusMap(),
      ));

    $form = new AphrontFormView();
    $form->setUser($user);
    $form->setID('maniphest-batch-edit-form');

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
      phutil_tag('p', array(), pht('These tasks will be edited:')));
    $form->appendChild($list);
    $form->appendChild(
      id(new AphrontFormInsetView())
        ->setTitle('Actions')
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
          ->addCancelButton('/maniphest/', 'Done'));

    $panel->appendChild($form);


    return $this->buildStandardPageResponse(
      $panel,
      array(
        'title' => pht('Batch Editor'),
      ));
  }

  private function buildTransactions($actions, ManiphestTask $task) {
    $value_map = array();
    $type_map = array(
      'add_comment'     => ManiphestTransactionType::TYPE_NONE,
      'assign'          => ManiphestTransactionType::TYPE_OWNER,
      'status'          => ManiphestTransactionType::TYPE_STATUS,
      'priority'        => ManiphestTransactionType::TYPE_PRIORITY,
      'add_project'     => ManiphestTransactionType::TYPE_PROJECTS,
      'remove_project'  => ManiphestTransactionType::TYPE_PROJECTS,
    );

    $edge_edit_types = array(
      'add_project'    => true,
      'remove_project' => true,
    );

    $xactions = array();
    foreach ($actions as $action) {
      if (empty($type_map[$action['action']])) {
        throw new Exception("Unknown batch edit action '{$action}'!");
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
          case ManiphestTransactionType::TYPE_NONE:
            $current = null;
            break;
          case ManiphestTransactionType::TYPE_OWNER:
            $current = $task->getOwnerPHID();
            break;
          case ManiphestTransactionType::TYPE_STATUS:
            $current = $task->getStatus();
            break;
          case ManiphestTransactionType::TYPE_PRIORITY:
            $current = $task->getPriority();
            break;
          case ManiphestTransactionType::TYPE_PROJECTS:
            $current = $task->getProjectPHIDs();
            break;
        }
      }

      // Check if the value is meaningful / provided, and normalize it if
      // necessary. This discards, e.g., empty comments and empty owner
      // changes.

      $value = $action['value'];
      switch ($type) {
        case ManiphestTransactionType::TYPE_NONE:
          if (!strlen($value)) {
            continue 2;
          }
          break;
        case ManiphestTransactionType::TYPE_OWNER:
          if (empty($value)) {
            continue 2;
          }
          $value = head($value);
          if ($value === ManiphestTaskOwner::OWNER_UP_FOR_GRABS) {
            $value = null;
          }
          break;
        case ManiphestTransactionType::TYPE_PROJECTS:
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
        case ManiphestTransactionType::TYPE_NONE:
          if (strlen($current)) {
            $value = $current."\n\n".$value;
          }
          break;
        case ManiphestTransactionType::TYPE_PROJECTS:
          $is_remove = ($action['action'] == 'remove_project');

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
      }

      $value_map[$type] = $value;
    }

    $template = new ManiphestTransaction();
    $template->setAuthorPHID($this->getRequest()->getUser()->getPHID());

    // TODO: Set content source to "batch edit".

    foreach ($value_map as $type => $value) {
      $xaction = clone $template;
      $xaction->setTransactionType($type);

      switch ($type) {
        case ManiphestTransactionType::TYPE_NONE:
          $xaction->setComments($value);
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
