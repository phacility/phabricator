<?php

/**
 * @group maniphest
 */
final class ManiphestTaskSummaryView extends ManiphestView {

  private $task;
  private $handles;
  private $user;
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

  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
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
      $batch =
        '<td class="maniphest-task-batch">'.
          javelin_render_tag(
            'input',
            array(
              'type'  => 'checkbox',
              'name'  => 'batch[]',
              'value' => $task->getID(),
              'sigil' => 'maniphest-batch',
            ),
            null).
        '</td>';
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

    $handle = javelin_render_tag(
      'td',
      array(
        'class' => 'maniphest-task-handle '.$pri_class.' '.$control_class,
        'sigil' => $control_sigil,
      ),
      '');

    return javelin_render_tag(
      'table',
      array(
        'class' => 'maniphest-task-summary',
        'sigil' => 'maniphest-task',
        'meta'  => array(
          'taskID' => $task->getID(),
        ),
      ),
      '<tr>'.
        $handle.
        $batch.
        '<td class="maniphest-task-number">'.
          'T'.$task->getID().
        '</td>'.
        '<td class="maniphest-task-status">'.
          idx($status_map, $task->getStatus(), 'Unknown').
        '</td>'.
        '<td class="maniphest-task-owner">'.
          ($task->getOwnerPHID()
            ? $handles[$task->getOwnerPHID()]->renderLink()
            : '<em>None</em>').
        '</td>'.
        '<td class="maniphest-task-name">'.
          phutil_render_tag(
            'a',
            array(
              'href' => '/T'.$task->getID(),
            ),
            phutil_escape_html($task->getTitle())).
        '</td>'.
        '<td class="maniphest-task-projects">'.
          $projects_view->render().
        '</td>'.
        '<td class="maniphest-task-updated">'.
          phabricator_date($task->getDateModified(), $this->user).
        '</td>'.
      '</tr>');
  }

}
