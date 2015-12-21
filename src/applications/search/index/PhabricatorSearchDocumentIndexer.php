<?php

abstract class PhabricatorSearchDocumentIndexer extends Phobject {

  private $context;

  protected function setContext($context) {
    $this->context = $context;
    return $this;
  }

  protected function getContext() {
    return $this->context;
  }

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

  public function indexDocumentByPHID($phid, $context) {
    $this->setContext($context);

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

  protected function indexTransactions(
    PhabricatorSearchAbstractDocument $doc,
    PhabricatorApplicationTransactionQuery $query,
    array $phids) {

    $xactions = id(clone $query)
      ->setViewer($this->getViewer())
      ->withObjectPHIDs($phids)
      ->execute();

    foreach ($xactions as $xaction) {
      if (!$xaction->hasComment()) {
        continue;
      }

      $comment = $xaction->getComment();
      $doc->addField(
        PhabricatorSearchDocumentFieldType::FIELD_COMMENT,
        $comment->getContent());
    }
  }

}
