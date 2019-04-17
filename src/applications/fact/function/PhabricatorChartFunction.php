<?php

abstract class PhabricatorChartFunction
  extends Phobject {

  final public function getFunctionKey() {
    return $this->getPhobjectClassConstant('FUNCTIONKEY', 32);
  }

  final public static function getAllFunctions() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getFunctionKey')
      ->execute();
  }

  final public function setArguments(array $arguments) {
    $this->newArguments($arguments);
    return $this;
  }

  abstract protected function newArguments(array $arguments);

}
