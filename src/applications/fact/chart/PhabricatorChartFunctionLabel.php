<?php

final class PhabricatorChartFunctionLabel
  extends Phobject {

  private $name;
  private $color;
  private $icon;
  private $fillColor;

  public function setName($name) {
    $this->name = $name;
    return $this;
  }

  public function getName() {
    return $this->name;
  }

  public function setColor($color) {
    $this->color = $color;
    return $this;
  }

  public function getColor() {
    return $this->color;
  }

  public function setIcon($icon) {
    $this->icon = $icon;
    return $this;
  }

  public function getIcon() {
    return $this->icon;
  }

  public function setFillColor($fill_color) {
    $this->fillColor = $fill_color;
    return $this;
  }

  public function getFillColor() {
    return $this->fillColor;
  }

  public function toWireFormat() {
    return array(
      'name' => $this->getName(),
      'color' => $this->getColor(),
      'icon' => $this->getIcon(),
      'fillColor' => $this->getFillColor(),
    );
  }

}
