<?php

final class PHUITwoColumnView extends AphrontTagView {

  private $mainColumn;
  private $sideColumn;
  private $display;
  private $header;

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

  public function setHeader(PHUIHeaderView $header) {
    $this->header = $header;
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

    $inner = phutil_tag_div('phui-two-column-row', $order);
    $table = phutil_tag_div('phui-two-column-content', $inner);

    $header = null;
    if ($this->header) {
      $header = phutil_tag_div('phui-two-column-header', $this->header);
    }

    return array($header, $table);
  }
}
