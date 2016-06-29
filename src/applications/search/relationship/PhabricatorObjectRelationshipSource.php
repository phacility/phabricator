<?php

abstract class PhabricatorObjectRelationshipSource extends Phobject {

  private $viewer;

  final public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  final public function getViewer() {
    return $this->viewer;
  }

  abstract public function isEnabledForObject($object);
  abstract public function getResultPHIDTypes();

}
