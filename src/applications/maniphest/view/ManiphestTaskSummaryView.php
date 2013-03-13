<?php

/**
 * @group maniphest
 */
final class ManiphestTaskSummaryView extends ManiphestView {

  private $task;
  private $handles;
  private $showBatchControls;
  private $showSubpriorityControls;

  public function setTask(ManiphestTask $task) {
    $this->task = $task;
    return $this;
  }

  public function setHandles(array $handles) {
    assert_instances_of($handles, 'PhabricatorObjectHandle');
    $this->handles = $handles;
    return $this;
  }

  public function setShowBatchControls($show_batch_controls) {
    $this->showBatchControls = $show_batch_controls;
    return $this;
  }

  public function setShowSubpriorityControls($show_subpriority_controls) {
    $this->showSubpriorityControls = $show_subpriority_controls;
    return $this;
  }

  public static function getPriorityClass($priority) {
    $classes = array(
      ManiphestTaskPriority::PRIORITY_UNBREAK_NOW => 'pri-unbreak',
      ManiphestTaskPriority::PRIORITY_TRIAGE => 'pri-triage',
      ManiphestTaskPriority::PRIORITY_HIGH => 'pri-high',
      ManiphestTaskPriority::PRIORITY_NORMAL => 'pri-normal',
      ManiphestTaskPriority::PRIORITY_LOW => 'pri-low',
      ManiphestTaskPriority::PRIORITY_WISH => 'pri-wish',
    );

    return idx($classes, $priority);
  }

  public function render() {

    if (!$this->user) {
      throw new Exception("Call setUser() before rendering!");
    }

    $task = $this->task;
    $handles = $this->handles;

    require_celerity_resource('maniphest-task-summary-css');

    $pri_class = self::getPriorityClass($task->getPriority());
    $status_map = ManiphestTaskStatus::getTaskStatusMap();

    $batch = null;
    if ($this->showBatchControls) {
      $batch = phutil_tag(
        'td',
        array(
          'rowspan' => 2,
          'class' => 'maniphest-task-batch',
        ),
        javelin_tag(
          'input',
          array(
            'type'  => 'checkbox',
            'name'  => 'batch[]',
            'value' => $task->getID(),
            'sigil' => 'maniphest-batch',
          )));
    }

    $projects_view = new ManiphestTaskProjectsView();
    $projects_view->setHandles(
      array_select_keys(
        $this->handles,
        $task->getProjectPHIDs()));

    $control_class = null;
    $control_sigil = null;
    if ($this->showSubpriorityControls) {
      $control_class = 'maniphest-active-handle';
      $control_sigil = 'maniphest-task-handle';
    }

    $handle = javelin_tag(
      'td',
      array(
        'rowspan' => 2,
        'class' => 'maniphest-task-handle '.$pri_class.' '.$control_class,
        'sigil' => $control_sigil,
      ),
      '');

    $task_name = phutil_tag(
      'span',
      array(
        'class' => 'maniphest-task-name',
      ),
      phutil_tag(
        'a',
        array(
          'href' => '/T'.$task->getID(),
        ),
        $task->getTitle()));

    $task_updated = phutil_tag(
      'span',
      array(
        'class' => 'maniphest-task-updated',
      ),
      phabricator_date($task->getDateModified(), $this->user));

    $task_info = phutil_tag(
      'td',
      array(
        'colspan' => 2,
        'class' => 'maniphest-task-number',
      ),
      array(
        'T'.$task->getID(),
        $task_name,
        $task_updated,
      ));

    $owner = '';
    if ($task->getOwnerPHID()) {
      $owner = pht('Assigned to %s',
        $handles[$task->getOwnerPHID()]->renderLink());
    }

    $task_owner = phutil_tag(
      'span',
      array(
        'class' => 'maniphest-task-owner',
      ),
      $task->getOwnerPHID()
        ? $owner
        : phutil_tag('em', array(), pht('None')));

    $task_status = phutil_tag(
      'td',
      array(
        'class' => 'maniphest-task-status',
      ),
      array(
        idx($status_map, $task->getStatus(), pht('Unknown')),
        $task_owner,
      ));

    $task_projects = phutil_tag(
      'td',
      array(
        'class' => 'maniphest-task-projects',
      ),
      $projects_view->render());

    $row1 = phutil_tag(
      'tr',
        array(),
        array(
          $handle,
          $batch,
          $task_info,
      ));

    $row2 = phutil_tag(
      'tr',
      array(),
      array(
        $task_status,
        $task_projects,
      ));

    return javelin_tag(
      'table',
      array(
        'class' => 'maniphest-task-summary',
        'sigil' => 'maniphest-task',
        'meta'  => array(
          'taskID' => $task->getID(),
        ),
      ),
      array(
        $row1,
        $row2,
      ));
  }

}
