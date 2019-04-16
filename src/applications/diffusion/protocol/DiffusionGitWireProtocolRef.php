<?php

final class DiffusionGitWireProtocolRef
  extends Phobject {

  private $name;
  private $hash;

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

  public function newSortVector() {
    return id(new PhutilSortVector())
      ->addString($this->getName());
  }

}
