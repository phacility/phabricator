<?php

abstract class PhabricatorEditEngineLock
  extends Phobject {

  private $viewer;
  private $object;

  final public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  final public function getViewer() {
    return $this->viewer;
  }

  final public function setObject($object) {
    $this->object = $object;
    return $this;
  }

  final public function getObject() {
    return $this->object;
  }

  public function willPromptUserForLockOverrideWithDialog(
    AphrontDialogView $dialog) {

    return $dialog
      ->setTitle(pht('Edit Locked Object'))
      ->appendParagraph(pht('This object is locked. Edit it anyway?'))
      ->addSubmitButton(pht('Override Lock'));
  }

  public function willBlockUserInteractionWithDialog(
    AphrontDialogView $dialog) {

    return $dialog
      ->setTitle(pht('Object Locked'))
      ->appendParagraph(
        pht('You can not interact with this object because it is locked.'));
  }

  public function getLockedObjectDisplayText() {
    return pht('This object has been locked.');
  }

  public static function newForObject(
    PhabricatorUser $viewer,
    $object) {

    if ($object instanceof PhabricatorEditEngineLockableInterface) {
      $lock = $object->newEditEngineLock();
    } else {
      $lock = new PhabricatorEditEngineDefaultLock();
    }

    return $lock
      ->setViewer($viewer)
      ->setObject($object);
  }



}
