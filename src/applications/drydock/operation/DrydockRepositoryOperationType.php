<?php

abstract class DrydockRepositoryOperationType extends Phobject {

  private $viewer;

  abstract public function applyOperation(
    DrydockRepositoryOperation $operation,
    DrydockInterface $interface);

  abstract public function getOperationDescription(
    DrydockRepositoryOperation $operation,
    PhabricatorUser $viewer);

  abstract public function getOperationCurrentStatus(
    DrydockRepositoryOperation $operation,
    PhabricatorUser $viewer);

  final public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  final public function getViewer() {
    return $this->viewer;
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
