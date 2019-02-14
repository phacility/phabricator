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

  /**
   * Do edits to this object REQUIRE that the user submit MFA?
   *
   * This is a strict requirement: users will need to add MFA to their accounts
   * if they don't already have it.
   *
   * @return bool True to strictly require MFA.
   */
  public function shouldRequireMFA() {
    return false;
  }

  /**
   * Should edits to this object prompt for MFA if it's available?
   *
   * This is advisory: users without MFA on their accounts will be able to
   * perform edits without being required to add MFA.
   *
   * @return bool True to prompt for MFA if available.
   */
  public function shouldTryMFA() {
    return false;
  }

}
