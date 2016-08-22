<?php

final class ManiphestTaskListView extends ManiphestView {

  private $tasks;
  private $handles;
  private $showBatchControls;
  private $showSubpriorityControls;
  private $noDataString;

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

  public function setNoDataString($text) {
    $this->noDataString = $text;
    return $this;
  }

  public function render() {
    $handles = $this->handles;

    require_celerity_resource('maniphest-task-summary-css');

    $list = new PHUIObjectItemListView();

    if ($this->noDataString) {
      $list->setNoDataString($this->noDataString);
    } else {
      $list->setNoDataString(pht('No tasks.'));
    }

    $status_map = ManiphestTaskStatus::getTaskStatusMap();
    $color_map = ManiphestTaskPriority::getColorMap();
    $priority_map = ManiphestTaskPriority::getTaskPriorityMap();

    if ($this->showBatchControls) {
      Javelin::initBehavior('maniphest-list-editor');
    }

    foreach ($this->tasks as $task) {
      $item = id(new PHUIObjectItemView())
        ->setUser($this->getUser())
        ->setObject($task)
        ->setObjectName('T'.$task->getID())
        ->setHeader($task->getTitle())
        ->setHref('/T'.$task->getID());

      if ($task->getOwnerPHID()) {
        $owner = $handles[$task->getOwnerPHID()];
        $item->addByline(pht('Assigned: %s', $owner->renderLink()));
      }

      $status = $task->getStatus();
      $pri = idx($priority_map, $task->getPriority());
      $status_name = idx($status_map, $task->getStatus());
      $tooltip = pht('%s, %s', $status_name, $pri);

      $icon = ManiphestTaskStatus::getStatusIcon($task->getStatus());
      $color = idx($color_map, $task->getPriority(), 'grey');
      if ($task->isClosed()) {
        $item->setDisabled(true);
        $color = 'grey';
      }

      $item->setStatusIcon($icon.' '.$color, $tooltip);

      $item->addIcon(
        'none',
        phabricator_datetime($task->getDateModified(), $this->getUser()));

      if ($this->showSubpriorityControls) {
        $item->setGrippable(true);
      }
      if ($this->showSubpriorityControls || $this->showBatchControls) {
        $item->addSigil('maniphest-task');
      }

      $project_handles = array_select_keys(
        $handles,
        array_reverse($task->getProjectPHIDs()));

      $item->addAttribute(
        id(new PHUIHandleTagListView())
          ->setLimit(4)
          ->setNoDataString(pht('No Projects'))
          ->setSlim(true)
          ->setHandles($project_handles));

      $item->setMetadata(
        array(
          'taskID' => $task->getID(),
        ));

      if ($this->showBatchControls) {
        $href = new PhutilURI('/maniphest/task/edit/'.$task->getID().'/');
        if (!$this->showSubpriorityControls) {
          $href->setQueryParam('ungrippable', 'true');
        }
        $item->addAction(
          id(new PHUIListItemView())
            ->setIcon('fa-pencil')
            ->addSigil('maniphest-edit-task')
            ->setHref($href));
      }

      $list->addItem($item);
    }

    return $list;
  }

  public static function loadTaskHandles(
    PhabricatorUser $viewer,
    array $tasks) {
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
      ->setViewer($viewer)
      ->withPHIDs($phids)
      ->execute();
  }

}
