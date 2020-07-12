<?php

final class FuelGridCellView
  extends FuelView {

  private $content;

  public function setContent($content) {
    $this->content = $content;
    return $this;
  }

  public function getContent() {
    return $this->content;
  }

  public function render() {
    $content = $this->getContent();

    return phutil_tag(
      'div',
      array(
        'class' => 'fuel-grid-cell',
      ),
      $content);
  }

}
