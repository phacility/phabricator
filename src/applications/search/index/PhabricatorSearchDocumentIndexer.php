<?php

abstract class PhabricatorSearchDocumentIndexer extends Phobject {

  abstract public function getIndexableObject();
  abstract protected function buildAbstractDocumentByPHID($phid);

  protected function getViewer() {
    return PhabricatorUser::getOmnipotentUser();
  }

  public function shouldIndexDocumentByPHID($phid) {
    $object = $this->getIndexableObject();
    return (phid_get_type($phid) == phid_get_type($object->generatePHID()));
  }

  public function getIndexIterator() {
    $object = $this->getIndexableObject();
    return new LiskMigrationIterator($object);
  }

  protected function loadDocumentByPHID($phid) {
    $object = id(new PhabricatorObjectQuery())
      ->setViewer($this->getViewer())
      ->withPHIDs(array($phid))
      ->executeOne();
    if (!$object) {
      throw new Exception(pht("Unable to load object by PHID '%s'!", $phid));
    }
    return $object;
  }

  public function indexDocumentByPHID($phid) {
    $document = $this->buildAbstractDocumentByPHID($phid);
    if ($document === null) {
      // This indexer doesn't build a document index, so we're done.
      return $this;
    }

    $object = $this->loadDocumentByPHID($phid);

    $extensions = PhabricatorFulltextEngineExtension::getAllExtensions();
    foreach ($extensions as $key => $extension) {
      if (!$extension->shouldIndexFulltextObject($object)) {
        unset($extensions[$key]);
      }
    }

    foreach ($extensions as $extension) {
      $extension->indexFulltextObject($object, $document);
    }

    $engine = PhabricatorSearchEngine::loadEngine();
    $engine->reindexAbstractDocument($document);

    return $this;
  }

  protected function newDocument($phid) {
    return id(new PhabricatorSearchAbstractDocument())
      ->setPHID($phid)
      ->setDocumentType(phid_get_type($phid));
  }

}
