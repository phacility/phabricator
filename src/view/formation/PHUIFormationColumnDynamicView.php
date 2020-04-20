<?php

abstract class PHUIFormationColumnDynamicView
  extends PHUIFormationColumnView {

  private $isVisible = true;
  private $isResizable;
  private $width;

  public function setIsVisible($is_visible) {
    $this->isVisible = $is_visible;
    return $this;
  }

  public function getIsVisible() {
    return $this->isVisible;
  }

  public function setIsResizable($is_resizable) {
    $this->isResizable = $is_resizable;
    return $this;
  }

  public function getIsResizable() {
    return $this->isResizable;
  }

  public function setWidth($width) {
    $this->width = $width;
    return $this;
  }

  public function getWidth() {
    return $this->width;
  }

}
