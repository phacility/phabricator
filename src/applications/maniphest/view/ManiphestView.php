<?php

/**
 * @group maniphest
 */
abstract class ManiphestView extends AphrontView {

  public static function renderTagForTask(ManiphestTask $task) {
    $status = $task->getStatus();
    $status_name = ManiphestTaskStatus::getTaskStatusFullName($status);
    $status_color = ManiphestTaskStatus::getTaskStatusTagColor($status);

    return id(new PhabricatorTagView())
        ->setType(PhabricatorTagView::TYPE_STATE)
        ->setName($status_name)
        ->setBackgroundColor($status_color);
  }

}
