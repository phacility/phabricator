<?php

abstract class PhabricatorFulltextEngine
  extends Phobject {

  private $object;

  public function setObject($object) {
    $this->object = $object;
    return $this;
  }

  public function getObject() {
    return $this->object;
  }

  protected function getViewer() {
    return PhabricatorUser::getOmnipotentUser();
  }

  abstract protected function buildAbstractDocument(
    PhabricatorSearchAbstractDocument $document,
    $object);

  final public function buildFulltextIndexes() {
    $object = $this->getObject();

    $extensions = PhabricatorFulltextEngineExtension::getAllExtensions();
    foreach ($extensions as $key => $extension) {
      if (!$extension->shouldIndexFulltextObject($object)) {
        unset($extensions[$key]);
      }
    }

    $document = $this->newAbstractDocument($object);

    $this->buildAbstractDocument($document, $object);

    foreach ($extensions as $extension) {
      $extension->indexFulltextObject($object, $document);
    }

    $storage_engine = PhabricatorFulltextStorageEngine::loadEngine();
    $storage_engine->reindexAbstractDocument($document);
  }

  protected function newAbstractDocument($object) {
    $phid = $object->getPHID();
    return id(new PhabricatorSearchAbstractDocument())
      ->setPHID($phid)
      ->setDocumentType(phid_get_type($phid));
  }

}
