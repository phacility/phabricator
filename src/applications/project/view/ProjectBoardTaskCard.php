<?php

final class ProjectBoardTaskCard {

  private $viewer;
  private $task;
  private $owner;
  private $canEdit;

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }
  public function getViewer() {
    return $this->viewer;
  }

  public function setTask(ManiphestTask $task) {
    $this->task = $task;
    return $this;
  }
  public function getTask() {
    return $this->task;
  }

  public function setOwner(PhabricatorObjectHandle $owner = null) {
    $this->owner = $owner;
    return $this;
  }
  public function getOwner() {
    return $this->owner;
  }

  public function setCanEdit($can_edit) {
    $this->canEdit = $can_edit;
    return $this;
  }
  public function getCanEdit() {
    return $this->canEdit;
  }

  public function getItem() {
    $task = $this->getTask();
    $owner = $this->getOwner();
    $can_edit = $this->getCanEdit();

    $color_map = ManiphestTaskPriority::getColorMap();
    $bar_color = idx($color_map, $task->getPriority(), 'grey');

    $card = id(new PHUIObjectItemView())
      ->setObjectName('T'.$task->getID())
      ->setHeader($task->getTitle())
      ->setGrippable($can_edit)
      ->setHref('/T'.$task->getID())
      ->addSigil('project-card')
      ->setMetadata(
        array(
          'objectPHID' => $task->getPHID(),
        ))
      ->addAction(
        id(new PHUIListItemView())
        ->setName(pht('Edit'))
        ->setIcon('edit')
        ->addSigil('edit-project-card')
        ->setHref('/maniphest/task/edit/'.$task->getID().'/'))
      ->setBarColor($bar_color);

    if ($owner) {
      $card->addAttribute($owner->renderLink());
    }

    return $card;
  }

}
