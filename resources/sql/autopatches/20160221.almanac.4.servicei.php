<?php

$table = new AlmanacService();

foreach (new LiskMigrationIterator($table) as $service) {
  PhabricatorSearchWorker::queueDocumentForIndexing(
    $service->getPHID(),
    array(
      'force' => true,
    ));
}
