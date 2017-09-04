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

    $enrich_extensions = array();
    $index_extensions = array();
    foreach ($extensions as $key => $extension) {
      if ($extension->shouldEnrichFulltextObject($object)) {
        $enrich_extensions[] = $extension;
      }

      if ($extension->shouldIndexFulltextObject($object)) {
        $index_extensions[] = $extension;
      }
    }

    $document = $this->newAbstractDocument($object);

    $this->buildAbstractDocument($document, $object);

    foreach ($enrich_extensions as $extension) {
      $extension->enrichFulltextObject($object, $document);
    }

    foreach ($index_extensions as $extension) {
      $extension->indexFulltextObject($object, $document);
    }

    PhabricatorSearchService::reindexAbstractDocument($document);
  }

  protected function newAbstractDocument($object) {
    $phid = $object->getPHID();
    return id(new PhabricatorSearchAbstractDocument())
      ->setPHID($phid)
      ->setDocumentType(phid_get_type($phid));
  }

}
