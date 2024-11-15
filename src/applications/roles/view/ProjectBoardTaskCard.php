<?php

final class RoleBoardTaskCard extends Phobject {

  private $viewer;
  private $roleHandles;
  private $task;
  private $owner;
  private $showEditControls;
  private $canEdit;
  private $coverImageFile;
  private $hideArchivedRoles;

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }
  public function getViewer() {
    return $this->viewer;
  }

  public function setRoleHandles(array $handles) {
    $this->roleHandles = $handles;
    return $this;
  }

  public function getRoleHandles() {
    return $this->roleHandles;
  }

  public function setCoverImageFile(PhabricatorFile $cover_image_file) {
    $this->coverImageFile = $cover_image_file;
    return $this;
  }

  public function getCoverImageFile() {
    return $this->coverImageFile;
  }

  public function setHideArchivedRoles($hide_archived_roles) {
    $this->hideArchivedRoles = $hide_archived_roles;
    return $this;
  }

  public function getHideArchivedRoles() {
    return $this->hideArchivedRoles;
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

  public function setShowEditControls($show_edit_controls) {
    $this->showEditControls = $show_edit_controls;
    return $this;
  }

  public function getShowEditControls() {
    return $this->showEditControls;
  }

  public function getItem() {
    $task = $this->getTask();
    $owner = $this->getOwner();
    $can_edit = $this->getCanEdit();
    $viewer = $this->getViewer();

    $color_map = ManiphestTaskPriority::getColorMap();
    $bar_color = idx($color_map, $task->getPriority(), 'grey');

    $card = id(new PHUIObjectItemView())
      ->setObject($task)
      ->setUser($viewer)
      ->setObjectName($task->getMonogram())
      ->setHeader($task->getTitle())
      ->setHref($task->getURI())
      ->addSigil('role-card')
      ->setDisabled($task->isClosed())
      ->setBarColor($bar_color);

    if ($this->getShowEditControls()) {
      if ($can_edit) {
        $card
          ->addSigil('draggable-card')
          ->addClass('draggable-card');
        $edit_icon = 'fa-pencil';
      } else {
        $card
          ->addClass('not-editable')
          ->addClass('undraggable-card');
        $edit_icon = 'fa-lock red';
      }

      $card->addAction(
        id(new PHUIListItemView())
          ->setName(pht('Edit'))
          ->setIcon($edit_icon)
          ->addSigil('edit-project-card')
          ->setHref('/maniphest/task/edit/'.$task->getID().'/'));
    }

    if ($owner) {
      $card->addHandleIcon($owner, $owner->getName());
    }

    $cover_file = $this->getCoverImageFile();
    if ($cover_file) {
      $card->setCoverImage($cover_file->getBestURI());
    }

    if (ManiphestTaskPoints::getIsEnabled()) {
      $points = $task->getPoints();
      if ($points !== null) {
        $points_tag = id(new PHUITagView())
          ->setType(PHUITagView::TYPE_SHADE)
          ->setColor(PHUITagView::COLOR_GREY)
          ->setSlimShady(true)
          ->setName($points)
          ->addClass('phui-workcard-points');
        $card->addAttribute($points_tag);
      }
    }

    $subtype = $task->newSubtypeObject();
    if ($subtype && $subtype->hasTagView()) {
      $subtype_tag = $subtype->newTagView()
        ->setSlimShady(true);
      $card->addAttribute($subtype_tag);
    }

    if ($task->isClosed()) {
      $icon = ManiphestTaskStatus::getStatusIcon($task->getStatus());
      $icon = id(new PHUIIconView())
        ->setIcon($icon.' grey');
      $card->addAttribute($icon);
      $card->setBarColor('grey');
    }

    $role_handles = $this->getRoleHandles();

    // Remove any archived roles from the list.
    if ($this->hideArchivedRoles) {
      if ($role_handles) {
        foreach ($role_handles as $key => $handle) {
          if ($handle->getStatus() == PhabricatorObjectHandle::STATUS_CLOSED) {
            unset($role_handles[$key]);
          }
        }
      }
    }

    if ($role_handles) {
      $role_handles = array_reverse($role_handles);
      $tag_list = id(new PHUIHandleTagListView())
        ->setSlim(true)
        ->setHandles($role_handles);
      $card->addAttribute($tag_list);
    }

    $card->addClass('phui-workcard');

    return $card;
  }

}
