<?php

/**
 * @group maniphest
 */
final class ManiphestTaskListView extends ManiphestView {

  private $tasks;
  private $handles;
  private $user;
  private $showBatchControls;
  private $showSubpriorityControls;

  public function setTasks(array $tasks) {
    assert_instances_of($tasks, 'ManiphestTask');
    $this->tasks = $tasks;
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

  public function render() {

    $views = array();
    foreach ($this->tasks as $task) {
      $view = new ManiphestTaskSummaryView();
      $view->setTask($task);
      $view->setShowBatchControls($this->showBatchControls);
      $view->setShowSubpriorityControls($this->showSubpriorityControls);
      $view->setUser($this->user);
      $view->setHandles($this->handles);
      $views[] = $view->render();
    }

    return implode("\n", $views);
  }

}
