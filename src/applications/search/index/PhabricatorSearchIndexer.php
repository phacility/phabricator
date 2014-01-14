<?php

final class PhabricatorSearchIndexer {

  public function queueDocumentForIndexing($phid) {
    PhabricatorWorker::scheduleTask(
      'PhabricatorSearchWorker',
      array(
        'documentPHID' => $phid,
      ));
  }

  public function indexDocumentByPHID($phid) {
    $doc_indexer_symbols = id(new PhutilSymbolLoader())
      ->setAncestorClass('PhabricatorSearchDocumentIndexer')
      ->setConcreteOnly(true)
      ->setType('class')
      ->selectAndLoadSymbols();

    $indexers = array();
    foreach ($doc_indexer_symbols as $symbol) {
      $indexers[] = newv($symbol['name'], array());
    }

    foreach ($indexers as $indexer) {
      if ($indexer->shouldIndexDocumentByPHID($phid)) {
        $indexer->indexDocumentByPHID($phid);
        break;
      }
    }

    return $this;
  }

}
