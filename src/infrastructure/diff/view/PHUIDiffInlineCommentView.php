<?php

abstract class PHUIDiffInlineCommentView extends AphrontView {

  private $isOnRight;

  public function getIsOnRight() {
    return $this->isOnRight;
  }

  public function setIsOnRight($on_right) {
    $this->isOnRight = $on_right;
    return $this;
  }

  public function getScaffoldCellID() {
    return null;
  }

  public function isHidden() {
    return false;
  }

  public function isHideable() {
    return true;
  }

  public function newHiddenIcon() {
    if ($this->isHideable()) {
      return new PHUIDiffRevealIconView();
    } else {
      return null;
    }
  }

}
