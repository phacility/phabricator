<?php

abstract class PhabricatorFulltextEngineExtension extends Phobject {

  final public function getExtensionKey() {
    return $this->getPhobjectClassConstant('EXTENSIONKEY');
  }

  final protected function getViewer() {
    return PhabricatorUser::getOmnipotentUser();
  }

  abstract public function getExtensionName();

  abstract public function shouldIndexFulltextObject($object);

  abstract public function indexFulltextObject(
    $object,
    PhabricatorSearchAbstractDocument $document);

  final public static function getAllExtensions() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getExtensionKey')
      ->execute();
  }

}
