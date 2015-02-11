<?php

final class PhabricatorSearchIndexer {

  public function queueDocumentForIndexing($phid, $context = null) {
    PhabricatorWorker::scheduleTask(
      'PhabricatorSearchWorker',
      array(
        'documentPHID' => $phid,
        'context' => $context,
      ),
      array(
        'priority' => PhabricatorWorker::PRIORITY_IMPORT,
      ));
  }

  public function indexDocumentByPHID($phid, $context) {
    $indexers = id(new PhutilSymbolLoader())
      ->setAncestorClass('PhabricatorSearchDocumentIndexer')
      ->loadObjects();

    foreach ($indexers as $indexer) {
      if ($indexer->shouldIndexDocumentByPHID($phid)) {
        $indexer->indexDocumentByPHID($phid, $context);
        break;
      }
    }

    return $this;
  }

}
