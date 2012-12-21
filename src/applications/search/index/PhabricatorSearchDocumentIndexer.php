<?php

/**
 * @group search
 */
abstract class PhabricatorSearchDocumentIndexer {

  abstract public function getIndexableObject();
  abstract protected function buildAbstractDocumentByPHID($phid);

  public function shouldIndexDocumentByPHID($phid) {
    $object = $this->getIndexableObject();
    return (phid_get_type($phid) == phid_get_type($object->generatePHID()));
  }

  public function getIndexIterator() {
    $object = $this->getIndexableObject();
    return new LiskMigrationIterator($object);
  }

  protected function loadDocumentByPHID($phid) {
    $object = $this->getIndexableObject();
    $document = $object->loadOneWhere('phid = %s', $phid);
    if (!$document) {
      throw new Exception("Unable to load document by phid '{$phid}'!");
    }
    return $document;
  }

  public function indexDocumentByPHID($phid) {
    try {
      $document = $this->buildAbstractDocumentByPHID($phid);

      $engine = PhabricatorSearchEngineSelector::newSelector()->newEngine();
      try {
        $engine->reindexAbstractDocument($document);
      } catch (Exception $ex) {
        $phid = $document->getPHID();
        $class = get_class($engine);

        phlog("Unable to index document {$phid} by engine {$class}.");
        phlog($ex);
      }

    } catch (Exception $ex) {
      $class = get_class($this);
      phlog("Unable to build document {$phid} by indexer {$class}.");
      phlog($ex);
    }

    return $this;
  }

}
