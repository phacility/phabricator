<?php

abstract class DoorkeeperBridge extends Phobject {

  private $viewer;

  final public function setViewer($viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  final public function getViewer() {
    return $this->viewer;
  }

  public function isEnabled() {
    return true;
  }

  abstract public function canPullRef(DoorkeeperObjectRef $ref);
  abstract public function pullRefs(array $refs);

  public function fillObjectFromData(DoorkeeperExternalObject $obj, $result) {
  }

}
