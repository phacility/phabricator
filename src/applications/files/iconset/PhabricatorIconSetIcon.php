<?php

final class PhabricatorIconSetIcon
  extends Phobject {

  private $key;
  private $icon;
  private $label;
  private $isDisabled;

  public function setKey($key) {
    $this->key = $key;
    return $this;
  }

  public function getKey() {
    return $this->key;
  }

  public function setIcon($icon) {
    $this->icon = $icon;
    return $this;
  }

  public function getIcon() {
    if ($this->icon === null) {
      return $this->getKey();
    }
    return $this->icon;
  }

  public function setIsDisabled($is_disabled) {
    $this->isDisabled = $is_disabled;
    return $this;
  }

  public function getIsDisabled() {
    return $this->isDisabled;
  }

  public function setLabel($label) {
    $this->label = $label;
    return $this;
  }

  public function getLabel() {
    return $this->label;
  }

}
