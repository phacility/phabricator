<?php

final class FuelMenuItemView
  extends FuelView {

  private $name;
  private $uri;
  private $icon;
  private $disabled;
  private $backgroundColor;

  public function setURI($uri) {
    $this->uri = $uri;
    return $this;
  }

  public function getURI() {
    return $this->uri;
  }

  public function setName($name) {
    $this->name = $name;
    return $this;
  }

  public function getName() {
    return $this->name;
  }

  public function setIcon(PHUIIconView $icon) {
    $this->icon = $icon;
    return $this;
  }

  public function getIcon() {
    return $this->icon;
  }

  public function newIcon() {
    $icon = new PHUIIconView();
    $this->setIcon($icon);
    return $icon;
  }

  public function setDisabled($disabled) {
    $this->disabled = $disabled;
    return $this;
  }

  public function getDisabled() {
    return $this->disabled;
  }

  public function setBackgroundColor($background_color) {
    $this->backgroundColor = $background_color;
    return $this;
  }

  public function getBackgroundColor() {
    return $this->backgroundColor;
  }

  public function render() {
    $icon = $this->getIcon();

    $name = $this->getName();
    $uri = $this->getURI();

    $icon = phutil_tag(
      'span',
      array(
        'class' => 'fuel-menu-item-icon',
      ),
      $icon);

    $item_link = phutil_tag(
      'a',
      array(
        'href' => $uri,
        'class' => 'fuel-menu-item-link',
      ),
      array(
        $icon,
        $name,
      ));

    $classes = array();
    $classes[] = 'fuel-menu-item';

    if ($this->getDisabled()) {
      $classes[] = 'disabled';
    }

    $background_color = $this->getBackgroundColor();
    if ($background_color !== null) {
      $classes[] = 'fuel-menu-item-background-color-'.$background_color;
    }


    if ($uri !== null) {
      $classes[] = 'has-link';
    }

    $classes = implode(' ', $classes);

    return phutil_tag(
      'div',
      array(
        'class' => $classes,
      ),
      $item_link);
  }

}
