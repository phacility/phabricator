<?php

abstract class PhabricatorEditEngineMFAEngine
  extends Phobject {

  private $object;
  private $viewer;

  public function setObject(PhabricatorEditEngineMFAInterface $object) {
    $this->object = $object;
    return $this;
  }

  public function getObject() {
    return $this->object;
  }

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function getViewer() {
    if (!$this->viewer) {
      throw new PhutilInvalidStateException('setViewer');
    }

    return $this->viewer;
  }

  final public static function newEngineForObject(
    PhabricatorEditEngineMFAInterface $object) {
    return $object->newEditEngineMFAEngine()
      ->setObject($object);
  }

  abstract public function shouldRequireMFA();

}
