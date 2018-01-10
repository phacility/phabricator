<?php

abstract class BulkParameterType extends Phobject {

  private $viewer;

  final public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  final public function getViewer() {
    return $this->viewer;
  }

  abstract public function getPHUIXControlType();

  public function getPHUIXControlSpecification() {
    return array(
      'value' => null,
    );
  }

}
