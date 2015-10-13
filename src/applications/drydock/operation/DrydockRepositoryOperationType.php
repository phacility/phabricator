<?php

abstract class DrydockRepositoryOperationType extends Phobject {

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
