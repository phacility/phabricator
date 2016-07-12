<?php

$table = new NuanceSource();

foreach (new LiskMigrationIterator($table) as $source) {
  PhabricatorSearchWorker::queueDocumentForIndexing(
    $source->getPHID(),
    array(
      'force' => true,
    ));
}
