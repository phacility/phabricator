<?php

final class PhabricatorLiskFulltextEngineExtension
  extends PhabricatorFulltextEngineExtension {

  const EXTENSIONKEY = 'lisk';

  public function getExtensionName() {
    return pht('Lisk Builtin Properties');
  }

  public function shouldIndexFulltextObject($object) {
    if (!($object instanceof PhabricatorLiskDAO)) {
      return false;
    }

    if (!$object->getConfigOption(LiskDAO::CONFIG_TIMESTAMPS)) {
      return false;
    }

    return true;
  }

  public function indexFulltextObject(
    $object,
    PhabricatorSearchAbstractDocument $document) {

    $document
      ->setDocumentCreated($object->getDateCreated())
      ->setDocumentModified($object->getDateModified());

  }

}
