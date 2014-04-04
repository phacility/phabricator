<?php

abstract class ManiphestView extends AphrontView {

  public static function renderTagForTask(ManiphestTask $task) {
    $status = $task->getStatus();
    $status_name = ManiphestTaskStatus::getTaskStatusFullName($status);

    return id(new PHUITagView())
        ->setType(PHUITagView::TYPE_STATE)
        ->setName($status_name);
  }

}
