<?php

final class DiffusionGitWireProtocolRefList
  extends Phobject {

  private $capabilities;
  private $refs = array();

  public function setCapabilities(
    DiffusionGitWireProtocolCapabilities $capabilities) {
    $this->capabilities = $capabilities;
    return $this;
  }

  public function getCapabilities() {
    return $this->capabilities;
  }

  public function addRef(DiffusionGitWireProtocolRef $ref) {
    $this->refs[] = $ref;
    return $this;
  }

  public function getRefs() {
    return $this->refs;
  }

}
