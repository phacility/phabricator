<?php

final class PHUIIconCircleView extends AphrontTagView {

  private $href = null;
  private $icon;
  private $color;
  private $size;

  const SMALL = 'circle-small';
  const MEDIUM = 'circle-medium';

  public function setHref($href) {
    $this->href = $href;
    return $this;
  }

  public function setIcon($icon) {
    $this->icon = $icon;
    return $this;
  }

  public function setColor($color) {
    $this->color = $color;
    return $this;
  }

  public function setSize($size) {
    $this->size = $size;
    return $this;
  }

  protected function getTagName() {
    $tag = 'span';
    if ($this->href) {
      $tag = 'a';
    }
    return $tag;
  }

  protected function getTagAttributes() {
    require_celerity_resource('phui-icon-view-css');

    $classes = array();
    $classes[] = 'phui-icon-circle';

    if ($this->color) {
      $classes[] = 'hover-'.$this->color;
    } else {
      $classes[] = 'hover-sky';
    }

    if ($this->size) {
      $classes[] = $this->size;
    }

    return array(
      'href' => $this->href,
      'class' => $classes,
    );
  }

  protected function getTagContent() {
    return id(new PHUIIconView())
      ->setIcon($this->icon);
  }

}
