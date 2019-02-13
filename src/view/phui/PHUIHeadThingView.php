<?php

final class PHUIHeadThingView extends AphrontTagView {

  private $image;
  private $imageHref;
  private $content;
  private $size;

  const SMALL = 'head-thing-small';
  const MEDIUM = 'head-thing-medium';

  public function setImageHref($href) {
    $this->imageHref = $href;
    return $this;
  }

  public function setImage($image) {
    $this->image = $image;
    return $this;
  }

  public function setContent($content) {
    $this->content = $content;
    return $this;
  }

  public function setSize($size) {
    $this->size = $size;
    return $this;
  }

  protected function getTagAttributes() {
    require_celerity_resource('phui-head-thing-view-css');

    $classes = array();
    $classes[] = 'phui-head-thing-view';
    if ($this->image) {
      $classes[] = 'phui-head-has-image';
    }

    if ($this->size) {
      $classes[] = $this->size;
    } else {
      $classes[] = self::SMALL;
    }

    return array(
      'class' => $classes,
    );
  }

  protected function getTagContent() {

    $image = javelin_tag(
      'a',
      array(
        'class' => 'phui-head-thing-image',
        'style' => 'background-image: url('.$this->image.');',
        'href' => $this->imageHref,
        'aural' => false,
      ));

    if ($this->image) {
      return array($image, $this->content);
    } else {
      return $this->content;
    }

  }

}
