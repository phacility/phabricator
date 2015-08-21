<?php

final class PhabricatorSearchIndexer extends Phobject {

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
    $indexers = id(new PhutilClassMapQuery())
      ->setAncestorClass('PhabricatorSearchDocumentIndexer')
      ->execute();

    foreach ($indexers as $indexer) {
      if ($indexer->shouldIndexDocumentByPHID($phid)) {
        $indexer->indexDocumentByPHID($phid, $context);
        break;
      }
    }

    return $this;
  }

}
