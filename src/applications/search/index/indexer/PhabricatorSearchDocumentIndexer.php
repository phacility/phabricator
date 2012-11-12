<?php

/**
 * @group search
 */
abstract class PhabricatorSearchDocumentIndexer {

  // TODO: Make this whole class tree concrete?
  final protected static function reindexAbstractDocument(
    PhabricatorSearchAbstractDocument $document) {
    $engine = PhabricatorSearchEngineSelector::newSelector()->newEngine();
    try {
      $engine->reindexAbstractDocument($document);
    } catch (Exception $ex) {
      $phid = $document->getPHID();
      $class = get_class($engine);
      phlog("Unable to index document {$phid} by engine {$class}.");
    }
  }

}
