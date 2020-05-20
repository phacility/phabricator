<?php

abstract class PhabricatorDestructionEngineExtension extends Phobject {

  final public function getExtensionKey() {
    return $this->getPhobjectClassConstant('EXTENSIONKEY');
  }

  abstract public function getExtensionName();

  public function canDestroyObject(
    PhabricatorDestructionEngine $engine,
    $object) {
    return true;
  }

  public function destroyObject(
    PhabricatorDestructionEngine $engine,
    $object) {
    return null;
  }

  public function didDestroyObject(
    PhabricatorDestructionEngine $engine,
    $object) {
    return null;
  }

  final public static function getAllExtensions() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getExtensionKey')
      ->execute();
  }

}
