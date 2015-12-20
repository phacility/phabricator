<?php

final class PhabricatorIndexEngine extends Phobject {

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
