<?php

final class PhabricatorDocumentEngineBlock
  extends Phobject {

  private $blockKey;
  private $content;
  private $classes = array();
  private $differenceHash;
  private $differenceType;
  private $isVisible;

  public function setContent($content) {
    $this->content = $content;
    return $this;
  }

  public function getContent() {
    return $this->content;
  }

  public function newContentView() {
    return $this->getContent();
  }

  public function setBlockKey($block_key) {
    $this->blockKey = $block_key;
    return $this;
  }

  public function getBlockKey() {
    return $this->blockKey;
  }

  public function addClass($class) {
    $this->classes[] = $class;
    return $this;
  }

  public function getClasses() {
    return $this->classes;
  }

  public function setDifferenceHash($difference_hash) {
    $this->differenceHash = $difference_hash;
    return $this;
  }

  public function getDifferenceHash() {
    return $this->differenceHash;
  }

  public function setDifferenceType($difference_type) {
    $this->differenceType = $difference_type;
    return $this;
  }

  public function getDifferenceType() {
    return $this->differenceType;
  }

  public function setIsVisible($is_visible) {
    $this->isVisible = $is_visible;
    return $this;
  }

  public function getIsVisible() {
    return $this->isVisible;
  }

}
