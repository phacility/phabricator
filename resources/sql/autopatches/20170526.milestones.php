<?php

$table = new PhabricatorProject();

foreach (new LiskMigrationIterator($table) as $project) {
  PhabricatorSearchWorker::queueDocumentForIndexing(
    $project->getPHID(),
    array(
      'force' => true,
    ));
}
