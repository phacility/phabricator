<?php

final class PHUITwoColumnView extends AphrontTagView {

  private $mainColumn;
  private $sideColumn;
  private $display;

  const DISPLAY_LEFT = 'phui-side-column-left';
  const DISPLAY_RIGHT = 'phui-side-column-right';

  public function setMainColumn($main) {
    $this->mainColumn = $main;
    return $this;
  }

  public function setSideColumn($side) {
    $this->sideColumn = $side;
    return $this;
  }

  public function setDisplay($display) {
    $this->display = $display;
    return $this;
  }

  public function getDisplay() {
    if ($this->display) {
      return $this->display;
    } else {
      return self::DISPLAY_RIGHT;
    }
  }

  protected function getTagAttributes() {
    $classes = array();
    $classes[] = 'phui-two-column-view';
    $classes[] = 'grouped';
    $classes[] = $this->getDisplay();

    return array(
      'class' => implode(' ', $classes),
    );
  }

  protected function getTagContent() {
    require_celerity_resource('phui-two-column-view-css');

    $main = phutil_tag(
      'div',
      array(
        'class' => 'phui-main-column',
      ),
      $this->mainColumn);

    $side = phutil_tag(
      'div',
      array(
        'class' => 'phui-side-column',
      ),
      $this->sideColumn);

    if ($this->getDisplay() == self::DISPLAY_LEFT) {
      $order = array($side, $main);
    } else {
      $order = array($main, $side);
    }

    return phutil_tag_div('phui-two-column-row', $order);
  }
}
