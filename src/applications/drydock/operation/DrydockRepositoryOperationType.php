<?php

abstract class DrydockRepositoryOperationType extends Phobject {

  private $viewer;
  private $operation;
  private $interface;

  abstract public function applyOperation(
    DrydockRepositoryOperation $operation,
    DrydockInterface $interface);

  abstract public function getOperationDescription(
    DrydockRepositoryOperation $operation,
    PhabricatorUser $viewer);

  abstract public function getOperationCurrentStatus(
    DrydockRepositoryOperation $operation,
    PhabricatorUser $viewer);

  public function getWorkingCopyMerges(DrydockRepositoryOperation $operation) {
    return array();
  }

  final public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  final public function getViewer() {
    return $this->viewer;
  }

  final public function setOperation(DrydockRepositoryOperation $operation) {
    $this->operation = $operation;
    return $this;
  }

  final public function getOperation() {
    return $this->operation;
  }

  final public function setInterface(DrydockInterface $interface) {
    $this->interface = $interface;
    return $this;
  }

  final public function getInterface() {
    if (!$this->interface) {
      throw new PhutilInvalidStateException('setInterface');
    }
    return $this->interface;
  }

  final public function getOperationConstant() {
    return $this->getPhobjectClassConstant('OPCONST', 32);
  }

  final public static function getAllOperationTypes() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getOperationConstant')
      ->execute();
  }

}
