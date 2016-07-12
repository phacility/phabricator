<?php

final class AphrontProgressBarView extends AphrontBarView {

  const WIDTH = 100;

  private $value;
  private $max = 100;
  private $alt = '';

  protected function getDefaultColor() {
    return parent::COLOR_AUTO_BADNESS;
  }

  public function setValue($value) {
    $this->value = $value;
    return $this;
  }

  public function setMax($max) {
    $this->max = $max;
    return $this;
  }

  public function setAlt($text) {
    $this->alt = $text;
    return $this;
  }

  protected function getRatio() {
    return min($this->value, $this->max) / $this->max;
  }

  public function render() {
    require_celerity_resource('aphront-bars');
    $ratio = $this->getRatio();
    $width = self::WIDTH * $ratio;

    $color = $this->getColor();

    return phutil_tag_div(
      "aphront-bar progress color-{$color}",
      array(
        phutil_tag(
          'div',
          array('title' => $this->alt),
          phutil_tag(
            'div',
            array('style' => "width: {$width}px;"),
            '')),
        phutil_tag(
          'span',
          array(),
          $this->getCaption()),
        ));
  }

}
