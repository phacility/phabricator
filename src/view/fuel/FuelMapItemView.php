<?php

final class FuelMapItemView
  extends AphrontView {

  private $name;
  private $value;

  public function setName($name) {
    $this->name = $name;
    return $this;
  }

  public function getName() {
    return $this->name;
  }

  public function setValue($value) {
    $this->value = $value;
    return $this;
  }

  public function getValue() {
    return $this->value;
  }

  public function render() {
    $value = $this->getValue();

    $view = array();

    $view[] = phutil_tag(
      'div',
      array(
        'class' => 'fuel-map-name',
      ),
      $this->getName());

    $view[] = phutil_tag(
      'div',
      array(
        'class' => 'fuel-map-value',
      ),
      $value);

    return phutil_tag(
      'div',
      array(
        'class' => 'fuel-map-pair',
      ),
      $view);
  }

}
