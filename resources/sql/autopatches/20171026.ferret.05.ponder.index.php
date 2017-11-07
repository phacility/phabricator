<?php

$table = new PonderQuestion();

foreach (new LiskMigrationIterator($table) as $question) {
  PhabricatorSearchWorker::queueDocumentForIndexing(
    $question->getPHID(),
    array(
      'force' => true,
    ));
}
