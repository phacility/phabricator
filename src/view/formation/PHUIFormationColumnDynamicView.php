<?php

abstract class PHUIFormationColumnDynamicView
  extends PHUIFormationColumnView {

  private $isVisible = true;
  private $isResizable;
  private $width;
  private $widthSettingKey;
  private $visibleSettingKey;
  private $minimumWidth;
  private $maximumWidth;
  private $expanderTooltip;

  public function setExpanderTooltip($expander_tooltip) {
    $this->expanderTooltip = $expander_tooltip;
    return $this;
  }

  public function getExpanderTooltip() {
    return $this->expanderTooltip;
  }

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

  public function setWidthSettingKey($width_setting_key) {
    $this->widthSettingKey = $width_setting_key;
    return $this;
  }

  public function getWidthSettingKey() {
    return $this->widthSettingKey;
  }

  public function setVisibleSettingKey($visible_setting_key) {
    $this->visibleSettingKey = $visible_setting_key;
    return $this;
  }

  public function getVisibleSettingKey() {
    return $this->visibleSettingKey;
  }

  public function setMinimumWidth($minimum_width) {
    $this->minimumWidth = $minimum_width;
    return $this;
  }

  public function getMinimumWidth() {
    return $this->minimumWidth;
  }

  public function setMaximumWidth($maximum_width) {
    $this->maximumWidth = $maximum_width;
    return $this;
  }

  public function getMaximumWidth() {
    return $this->maximumWidth;
  }

}
