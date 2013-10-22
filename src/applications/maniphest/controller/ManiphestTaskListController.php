<?php

final class ManiphestTaskListController
  extends ManiphestController
  implements PhabricatorApplicationSearchResultsControllerInterface {

  private $queryKey;

  public function shouldAllowPublic() {
    return true;
  }

  public function willProcessRequest(array $data) {
    $this->queryKey = idx($data, 'queryKey');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $controller = id(new PhabricatorApplicationSearchController($request))
      ->setQueryKey($this->queryKey)
      ->setSearchEngine(new ManiphestTaskSearchEngine())
      ->setNavigation($this->buildSideNavView());

    return $this->delegateToController($controller);
  }

  public function renderResultsList(
    array $tasks,
    PhabricatorSavedQuery $query) {
    assert_instances_of($tasks, 'ManiphestTask');

    $viewer = $this->getRequest()->getUser();

    // If we didn't match anything, just pick up the default empty state.
    if (!$tasks) {
      return id(new PHUIObjectItemListView())
        ->setUser($viewer);
    }

    $group_parameter = nonempty($query->getParameter('group'), 'priority');
    $order_parameter = nonempty($query->getParameter('order'), 'priority');

    $handles = $this->loadTaskHandles($tasks);
    $groups = $this->groupTasks(
      $tasks,
      $group_parameter,
      $handles);

    $can_edit_priority = $this->hasApplicationCapability(
      ManiphestCapabilityEditPriority::CAPABILITY);

    $can_drag = ($order_parameter == 'priority') &&
                ($can_edit_priority) &&
                ($group_parameter == 'none' || $group_parameter == 'priority');

    if (!$viewer->isLoggedIn()) {
      // TODO: (T603) Eventually, we conceivably need to make each task
      // draggable individually, since the user may be able to edit some but
      // not others.
      $can_drag = false;
    }

    $result = array();

    $lists = array();
    foreach ($groups as $group => $list) {
      $task_list = new ManiphestTaskListView();
      $task_list->setShowBatchControls(true);
      if ($can_drag) {
        $task_list->setShowSubpriorityControls(true);
      }
      $task_list->setUser($viewer);
      $task_list->setTasks($list);
      $task_list->setHandles($handles);

      $header = javelin_tag(
        'h1',
        array(
          'class' => 'maniphest-task-group-header',
          'sigil' => 'task-group',
          'meta'  => array(
            'priority' => head($list)->getPriority(),
          ),
        ),
        pht('%s (%s)', $group, new PhutilNumber(count($list))));

      $lists[] = phutil_tag(
        'div',
        array(
          'class' => 'maniphest-task-group'
        ),
        array(
          $header,
          $task_list,
        ));
    }

    if ($can_drag) {
      Javelin::initBehavior(
        'maniphest-subpriority-editor',
        array(
          'uri'   =>  '/maniphest/subpriority/',
        ));
    }

    return phutil_tag(
      'div',
      array(
        'class' => 'maniphest-list-container',
      ),
      array(
        $lists,
        $this->renderBatchEditor($query),
      ));
  }

  private function loadTaskHandles(array $tasks) {
    assert_instances_of($tasks, 'ManiphestTask');

    $phids = array();
    foreach ($tasks as $task) {
      $assigned_phid = $task->getOwnerPHID();
      if ($assigned_phid) {
        $phids[] = $assigned_phid;
      }
      foreach ($task->getProjectPHIDs() as $project_phid) {
        $phids[] = $project_phid;
      }
    }

    if (!$phids) {
      return array();
    }

    return id(new PhabricatorHandleQuery())
      ->setViewer($this->getRequest()->getUser())
      ->withPHIDs($phids)
      ->execute();
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
          return pht('(No Project)');
        }
      default:
        return pht('Tasks');
    }
  }

  private function renderBatchEditor(PhabricatorSavedQuery $saved_query) {
    $user = $this->getRequest()->getUser();

    $batch_capability = ManiphestCapabilityBulkEdit::CAPABILITY;
    if (!$this->hasApplicationCapability($batch_capability)) {
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
      '<div class="maniphest-batch-editor">'.
        '<div class="batch-editor-header">%s</div>'.
        '<table class="maniphest-batch-editor-layout">'.
          '<tr>'.
            '<td>%s%s</td>'.
            '<td>%s</td>'.
            '<td id="batch-select-status-cell">%s</td>'.
            '<td class="batch-select-submit-cell">%s%s</td>'.
          '</tr>'.
        '</table>'.
      '</div>',
      pht('Batch Task Editor'),
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

    return $editor;
  }

}
