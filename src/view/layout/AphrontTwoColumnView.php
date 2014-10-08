<?php

final class AphrontTwoColumnView extends AphrontView {

  private $mainColumn;
  private $sideColumn;
  private $centered = false;
  private $padding = true;

  public function setMainColumn($main) {
    $this->mainColumn = $main;
    return $this;
  }

  public function setSideColumn($side) {
    $this->sideColumn = $side;
    return $this;
  }

  public function setCentered($centered) {
    $this->centered = $centered;
    return $this;
  }

  public function setNoPadding($padding) {
    $this->padding = $padding;
    return $this;
  }

  public function render() {
    require_celerity_resource('aphront-two-column-view-css');

    $main = phutil_tag(
      'div',
      array(
        'class' => 'aphront-main-column',
      ),
      $this->mainColumn);

    $side = phutil_tag(
      'div',
      array(
        'class' => 'aphront-side-column',
      ),
      $this->sideColumn);

    $classes = array('aphront-two-column');
    if ($this->centered) {
      $classes = array('aphront-two-column-centered');
    }

    if ($this->padding) {
      $classes[] = 'aphront-two-column-padded';
    }

    return phutil_tag(
      'div',
      array(
        'class' => implode(' ', $classes),
      ),
      array(
        $main,
        $side,
      ));
  }
}
