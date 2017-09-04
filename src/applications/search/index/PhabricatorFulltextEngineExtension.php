<?php

abstract class PhabricatorFulltextEngineExtension extends Phobject {

  final public function getExtensionKey() {
    return $this->getPhobjectClassConstant('EXTENSIONKEY');
  }

  final protected function getViewer() {
    return PhabricatorUser::getOmnipotentUser();
  }

  abstract public function getExtensionName();

  public function shouldEnrichFulltextObject($object) {
    return false;
  }

  public function enrichFulltextObject(
    $object,
    PhabricatorSearchAbstractDocument $document) {
    return;
  }

  public function shouldIndexFulltextObject($object) {
    return false;
  }

  public function indexFulltextObject(
    $object,
    PhabricatorSearchAbstractDocument $document) {
    return;
  }

  final public static function getAllExtensions() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getExtensionKey')
      ->execute();
  }

}
