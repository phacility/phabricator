<?php

final class PhabricatorIconSetIcon
  extends Phobject {

  private $key;
  private $icon;
  private $label;

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

  public function setLabel($label) {
    $this->label = $label;
    return $this;
  }

  public function getLabel() {
    return $this->label;
  }

}
