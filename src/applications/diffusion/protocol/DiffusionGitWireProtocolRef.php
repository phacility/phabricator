<?php

final class DiffusionGitWireProtocolRef
  extends Phobject {

  private $name;
  private $hash;
  private $isShallow;

  public function setName($name) {
    $this->name = $name;
    return $this;
  }

  public function getName() {
    return $this->name;
  }

  public function setHash($hash) {
    $this->hash = $hash;
    return $this;
  }

  public function getHash() {
    return $this->hash;
  }

  public function setIsShallow($is_shallow) {
    $this->isShallow = $is_shallow;
    return $this;
  }

  public function getIsShallow() {
    return $this->isShallow;
  }

  public function newSortVector() {
    return id(new PhutilSortVector())
      ->addInt((int)$this->getIsShallow())
      ->addString((string)$this->getName());
  }

}
