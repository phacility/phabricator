<?php

/**
 * @group maniphest
 */
final class ManiphestTaskListView extends ManiphestView {

  private $tasks;
  private $handles;
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

  public function setShowBatchControls($show_batch_controls) {
    $this->showBatchControls = $show_batch_controls;
    return $this;
  }

  public function setShowSubpriorityControls($show_subpriority_controls) {
    $this->showSubpriorityControls = $show_subpriority_controls;
    return $this;
  }

  public function render() {
    $handles = $this->handles;

    $list = new PhabricatorObjectItemListView();
    $list->setCards(true);
    $list->setFlush(true);

    $status_map = ManiphestTaskStatus::getTaskStatusMap();
    $color_map = array(
      ManiphestTaskPriority::PRIORITY_UNBREAK_NOW => 'magenta',
      ManiphestTaskPriority::PRIORITY_TRIAGE => 'violet',
      ManiphestTaskPriority::PRIORITY_HIGH => 'red',
      ManiphestTaskPriority::PRIORITY_NORMAL => 'orange',
      ManiphestTaskPriority::PRIORITY_LOW => 'yellow',
      ManiphestTaskPriority::PRIORITY_WISH => 'sky',
    );

    foreach ($this->tasks as $task) {
      $item = new PhabricatorObjectItemView();
      $item->setObjectName('T'.$task->getID());
      $item->setHeader($task->getTitle());
      $item->setHref('/T'.$task->getID());

      if ($task->getOwnerPHID()) {
        $owner = $handles[$task->getOwnerPHID()];
        $item->addByline(pht('Assigned: %s', $owner->renderLink()));
      }

      $status = $task->getStatus();
      if ($status != ManiphestTaskStatus::STATUS_OPEN) {
        $item->addFootIcon(
          ($status == ManiphestTaskStatus::STATUS_CLOSED_RESOLVED)
            ? 'enable-white'
            : 'delete-white',
          idx($status_map, $status, 'Unknown'));
      }

      $item->setBarColor(idx($color_map, $task->getPriority(), 'grey'));

      $item->addIcon(
        'none',
        phabricator_datetime($task->getDateModified(), $this->getUser()));

      if ($this->showSubpriorityControls) {
        $item->setGrippable(true);
      }
      if ($this->showSubpriorityControls || $this->showBatchControls) {
        $item->addSigil('maniphest-task');
      }

      if ($task->getProjectPHIDs()) {
        $projects_view = new ManiphestTaskProjectsView();
        $projects_view->setHandles(
          array_select_keys(
            $handles,
            $task->getProjectPHIDs()));

        $item->addAttribute($projects_view);
      }

      $item->setMetadata(
        array(
          'taskID' => $task->getID(),
        ));

      $list->addItem($item);
    }

    return $list;
  }

}
