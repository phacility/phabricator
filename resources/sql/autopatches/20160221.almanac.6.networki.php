<?php

$table = new AlmanacNetwork();

foreach (new LiskMigrationIterator($table) as $network) {
  PhabricatorSearchWorker::queueDocumentForIndexing(
    $network->getPHID(),
    array(
      'force' => true,
    ));
}
