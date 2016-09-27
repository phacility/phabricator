<?php

$table = new PhabricatorPhurlURL();

foreach (new LiskMigrationIterator($table) as $url) {
  PhabricatorSearchWorker::queueDocumentForIndexing(
    $url->getPHID(),
    array(
      'force' => true,
    ));
}
