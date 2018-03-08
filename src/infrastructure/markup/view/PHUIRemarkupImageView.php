<?php

final class PHUIRemarkupImageView
  extends AphrontView {

  private $uri;
  private $width;
  private $height;
  private $alt;
  private $classes = array();

  public function setURI($uri) {
    $this->uri = $uri;
    return $this;
  }

  public function getURI() {
    return $this->uri;
  }

  public function setWidth($width) {
    $this->width = $width;
    return $this;
  }

  public function getWidth() {
    return $this->width;
  }

  public function setHeight($height) {
    $this->height = $height;
    return $this;
  }

  public function getHeight() {
    return $this->height;
  }

  public function setAlt($alt) {
    $this->alt = $alt;
    return $this;
  }

  public function getAlt() {
    return $this->alt;
  }

  public function addClass($class) {
    $this->classes[] = $class;
    return $this;
  }

  public function render() {
    $id = celerity_generate_unique_node_id();

    Javelin::initBehavior(
      'remarkup-load-image',
      array(
        'uri' => (string)$this->uri,
        'imageID' => $id,
      ));

    $classes = null;
    if ($this->classes) {
      $classes = implode(' ', $this->classes);
    }

    return phutil_tag(
      'img',
      array(
        'id' => $id,
        'width' => $this->getWidth(),
        'height' => $this->getHeight(),
        'alt' => $this->getAlt(),
        'class' => $classes,
      ));
  }

}
