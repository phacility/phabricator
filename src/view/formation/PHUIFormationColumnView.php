<?php

abstract class PHUIFormationColumnView
  extends AphrontAutoIDView {

  private $item;
  private $isDesktopOnly;

  final public function setColumnItem(PHUIFormationColumnItem $item) {
    $this->item = $item;
    return $this;
  }

  final public function getColumnItem() {
    return $this->item;
  }

  public function getWidth() {
    return null;
  }

  public function getIsResizable() {
    return false;
  }

  public function getIsVisible() {
    return true;
  }

  public function getIsControlColumn() {
    return false;
  }

  public function getVisibleSettingKey() {
    return null;
  }

  public function getWidthSettingKey() {
    return null;
  }

  public function getMinimumWidth() {
    return null;
  }

  public function getMaximumWidth() {
    return null;
  }

  public function newClientProperties() {
    return null;
  }

  public function getExpanderTooltip() {
    return null;
  }

  public function setIsDesktopOnly($is_desktop_only) {
    $this->isDesktopOnly = $is_desktop_only;
    return $this;
  }

  public function getIsDesktopOnly() {
    return $this->isDesktopOnly;
  }

}
