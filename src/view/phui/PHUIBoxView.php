<?php

final class PHUIBoxView extends AphrontTagView {

  private $margin = array();
  private $padding = array();
  private $shadow = false;

  public function addMargin($margin) {
    $this->margin[] = $margin;
    return $this;
  }

  public function addPadding($padding) {
    $this->padding[] = $padding;
    return $this;
  }

  public function setShadow($shadow) {
    $this->shadow = $shadow;
    return $this;
  }

  public function getTagContent() {

    require_celerity_resource('phui-box-css');

    $outer_classes = array();
    $outer_classes[] = 'phui-box';
    if ($this->shadow) {
      $outer_classes[] = 'phui-box-shadow';
    }
    foreach ($this->margin as $margin) {
      $outer_classes[] = $margin;
    }

    $inner_classes = array();
    $inner_classes[] = 'phui-box-inner';
    foreach ($this->padding as $padding) {
      $inner_classes[] = $padding;
    }

    $inner = phutil_tag(
      'div',
        array(
          'class' => implode(' ', $inner_classes)
        ),
        $this->renderChildren());

    return phutil_tag(
      'div',
        array(
          'class' => implode(' ', $outer_classes)
        ),
        $inner);
  }
}
