<?php

final class ManiphestTaskResultListView extends ManiphestView {

  private $tasks;
  private $savedQuery;
  private $canEditPriority;
  private $canBatchEdit;
  private $showBatchControls;

  public function setSavedQuery(PhabricatorSavedQuery $query) {
    $this->savedQuery = $query;
    return $this;
  }

  public function setTasks(array $tasks) {
    $this->tasks = $tasks;
    return $this;
  }

  public function setCanEditPriority($can_edit_priority) {
    $this->canEditPriority = $can_edit_priority;
    return $this;
  }

  public function setCanBatchEdit($can_batch_edit) {
    $this->canBatchEdit = $can_batch_edit;
    return $this;
  }

  public function setShowBatchControls($show_batch_controls) {
    $this->showBatchControls = $show_batch_controls;
    return $this;
  }

  public function render() {
    $viewer = $this->getUser();
    $tasks = $this->tasks;
    $query = $this->savedQuery;

    // If we didn't match anything, just pick up the default empty state.
    if (!$tasks) {
      return id(new PHUIObjectItemListView())
        ->setUser($viewer);
    }

    $group_parameter = nonempty($query->getParameter('group'), 'priority');
    $order_parameter = nonempty($query->getParameter('order'), 'priority');

    $handles = ManiphestTaskListView::loadTaskHandles($viewer, $tasks);
    $groups = $this->groupTasks(
      $tasks,
      $group_parameter,
      $handles);

    $can_edit_priority = $this->canEditPriority;

    $can_drag = ($order_parameter == 'priority') &&
                ($can_edit_priority) &&
                ($group_parameter == 'none' || $group_parameter == 'priority');

    if (!$viewer->isLoggedIn()) {
      // TODO: (T7131) Eventually, we conceivably need to make each task
      // draggable individually, since the user may be able to edit some but
      // not others.
      $can_drag = false;
    }

    $result = array();

    $lists = array();
    foreach ($groups as $group => $list) {
      $task_list = new ManiphestTaskListView();
      $task_list->setShowBatchControls($this->showBatchControls);
      if ($can_drag) {
        $task_list->setShowSubpriorityControls(true);
      }
      $task_list->setUser($viewer);
      $task_list->setTasks($list);
      $task_list->setHandles($handles);

      $header = id(new PHUIHeaderView())
        ->addSigil('task-group')
        ->setMetadata(array('priority' => head($list)->getPriority()))
        ->setHeader(pht('%s (%s)', $group, phutil_count($list)));

      $lists[] = id(new PHUIObjectBoxView())
        ->setHeader($header)
        ->setObjectList($task_list);

    }

    if ($can_drag) {
      Javelin::initBehavior(
        'maniphest-subpriority-editor',
        array(
          'uri'   =>  '/maniphest/subpriority/',
        ));
    }

    return array(
      $lists,
      $this->showBatchControls ? $this->renderBatchEditor($query) : null,
    );
  }


  private function groupTasks(array $tasks, $group, array $handles) {
    assert_instances_of($tasks, 'ManiphestTask');
    assert_instances_of($handles, 'PhabricatorObjectHandle');

    $groups = $this->getTaskGrouping($tasks, $group);

    $results = array();
    foreach ($groups as $label_key => $tasks) {
      $label = $this->getTaskLabelName($group, $label_key, $handles);
      $results[$label][] = $tasks;
    }
    foreach ($results as $label => $task_groups) {
      $results[$label] = array_mergev($task_groups);
    }

    return $results;
  }

  private function getTaskGrouping(array $tasks, $group) {
    switch ($group) {
      case 'priority':
        return mgroup($tasks, 'getPriority');
      case 'status':
        return mgroup($tasks, 'getStatus');
      case 'assigned':
        return mgroup($tasks, 'getOwnerPHID');
      case 'project':
        return mgroup($tasks, 'getGroupByProjectPHID');
      default:
        return array(pht('Tasks') => $tasks);
    }
  }

  private function getTaskLabelName($group, $label_key, array $handles) {
    switch ($group) {
      case 'priority':
        return ManiphestTaskPriority::getTaskPriorityName($label_key);
      case 'status':
        return ManiphestTaskStatus::getTaskStatusFullName($label_key);
      case 'assigned':
        if ($label_key) {
          return $handles[$label_key]->getFullName();
        } else {
          return pht('(Not Assigned)');
        }
      case 'project':
        if ($label_key) {
          return $handles[$label_key]->getFullName();
        } else {
          // This may mean "No Projects", or it may mean the query has project
          // constraints but the task is only in constrained projects (in this
          // case, we don't show the group because it would always have all
          // of the tasks). Since distinguishing between these two cases is
          // messy and the UI is reasonably clear, label generically.
          return pht('(Ungrouped)');
        }
      default:
        return pht('Tasks');
    }
  }

  private function renderBatchEditor(PhabricatorSavedQuery $saved_query) {
    $user = $this->getUser();

    if (!$this->canBatchEdit) {
      return null;
    }

    if (!$user->isLoggedIn()) {
      // Don't show the batch editor or excel export for logged-out users.
      // Technically we //could// let them export, but ehh.
      return null;
    }

    Javelin::initBehavior(
      'maniphest-batch-selector',
      array(
        'selectAll'   => 'batch-select-all',
        'selectNone'  => 'batch-select-none',
        'submit'      => 'batch-select-submit',
        'status'      => 'batch-select-status-cell',
        'idContainer' => 'batch-select-id-container',
        'formID'      => 'batch-select-form',
      ));

    $select_all = javelin_tag(
      'a',
      array(
        'href'        => '#',
        'mustcapture' => true,
        'class'       => 'grey button',
        'id'          => 'batch-select-all',
      ),
      pht('Select All'));

    $select_none = javelin_tag(
      'a',
      array(
        'href'        => '#',
        'mustcapture' => true,
        'class'       => 'grey button',
        'id'          => 'batch-select-none',
      ),
      pht('Clear Selection'));

    $submit = phutil_tag(
      'button',
      array(
        'id'          => 'batch-select-submit',
        'disabled'    => 'disabled',
        'class'       => 'disabled',
      ),
      pht("Batch Edit Selected \xC2\xBB"));

    $export = javelin_tag(
      'a',
      array(
        'href' => '/maniphest/export/'.$saved_query->getQueryKey().'/',
        'class' => 'grey button',
      ),
      pht('Export to Excel'));

    $hidden = phutil_tag(
      'div',
      array(
        'id' => 'batch-select-id-container',
      ),
      '');

    $editor = hsprintf(
        '<table class="maniphest-batch-editor-layout">'.
          '<tr>'.
            '<td>%s%s</td>'.
            '<td>%s</td>'.
            '<td id="batch-select-status-cell">%s</td>'.
            '<td class="batch-select-submit-cell">%s%s</td>'.
          '</tr>'.
        '</table>',
      $select_all,
      $select_none,
      $export,
      '',
      $submit,
      $hidden);

    $editor = phabricator_form(
      $user,
      array(
        'method' => 'POST',
        'action' => '/maniphest/batch/',
        'id'     => 'batch-select-form',
      ),
      $editor);

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Batch Task Editor'))
      ->appendChild($editor);

    $content = phutil_tag_div('maniphest-batch-editor', $box);

    return $content;
  }
}
