<?php

$table = new DrydockBlueprint();

foreach (new LiskMigrationIterator($table) as $blueprint) {
  PhabricatorSearchWorker::queueDocumentForIndexing(
    $blueprint->getPHID(),
    array(
      'force' => true,
    ));
}
