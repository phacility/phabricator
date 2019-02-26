<?php

final class HarbormasterBuildPlanBehaviorOption
  extends Phobject {

  private $name;
  private $key;
  private $icon;
  private $description;
  private $isDefault;

  public function setName($name) {
    $this->name = $name;
    return $this;
  }

  public function getName() {
    return $this->name;
  }

  public function setKey($key) {
    $this->key = $key;
    return $this;
  }

  public function getKey() {
    return $this->key;
  }

  public function setDescription($description) {
    $this->description = $description;
    return $this;
  }

  public function getDescription() {
    return $this->description;
  }

  public function setIsDefault($is_default) {
    $this->isDefault = $is_default;
    return $this;
  }

  public function getIsDefault() {
    return $this->isDefault;
  }

  public function setIcon($icon) {
    $this->icon = $icon;
    return $this;
  }

  public function getIcon() {
    return $this->icon;
  }

}
