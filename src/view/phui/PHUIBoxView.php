<?php

final class PHUIBoxView extends AphrontTagView {

  private $margin = array();
  private $padding = array();
  private $border = false;

  public function addMargin($margin) {
    $this->margin[] = $margin;
    return $this;
  }

  public function addPadding($padding) {
    $this->padding[] = $padding;
    return $this;
  }

  public function setBorder($border) {
    $this->border = $border;
    return $this;
  }

  protected function getTagAttributes() {
    require_celerity_resource('phui-box-css');
    $outer_classes = array();
    $outer_classes[] = 'phui-box';
    if ($this->border) {
      $outer_classes[] = 'phui-box-border';
    }
    foreach ($this->margin as $margin) {
      $outer_classes[] = $margin;
    }
    foreach ($this->padding as $padding) {
      $outer_classes[] = $padding;
    }
    return array('class' => $outer_classes);
  }

  protected function getTagName() {
    return 'div';
  }

  protected function getTagContent() {
    return $this->renderChildren();
  }
}
