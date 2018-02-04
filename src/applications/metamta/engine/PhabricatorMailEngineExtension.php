<?php

abstract class PhabricatorMailEngineExtension
  extends Phobject {

  private $viewer;
  private $editor;

  final public function getExtensionKey() {
    return $this->getPhobjectClassConstant('EXTENSIONKEY');
  }

  final public function setViewer($viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  final public function getViewer() {
    return $this->viewer;
  }

  final public function setEditor(
    PhabricatorApplicationTransactionEditor $editor) {
    $this->editor = $editor;
    return $this;
  }

  final public function getEditor() {
    return $this->editor;
  }

  abstract public function supportsObject($object);
  abstract public function newMailStampTemplates($object);
  abstract public function newMailStamps($object, array $xactions);

  final public static function getAllExtensions() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getExtensionKey')
      ->execute();
  }

  final protected function getMailStamp($key) {
    return $this->getEditor()->getMailStamp($key);
  }

}
