<?php

$table = new ConpherenceThread();

foreach (new LiskMigrationIterator($table) as $thread) {
  PhabricatorSearchWorker::queueDocumentForIndexing(
    $thread->getPHID(),
    array(
      'force' => true,
    ));
}
