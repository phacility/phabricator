<?php

abstract class PhabricatorDestructionEngineExtension extends Phobject {

  final public function getExtensionKey() {
    return $this->getPhobjectClassConstant('EXTENSIONKEY');
  }

  abstract public function getExtensionName();
  abstract public function canDestroyObject(
    PhabricatorDestructionEngine $engine,
    $object);
  abstract public function destroyObject(
    PhabricatorDestructionEngine $engine,
    $object);

  final public static function getAllExtensions() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getExtensionKey')
      ->execute();
  }

}
