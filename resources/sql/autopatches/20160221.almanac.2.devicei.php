<?php

$table = new AlmanacDevice();

foreach (new LiskMigrationIterator($table) as $device) {
  PhabricatorSearchWorker::queueDocumentForIndexing(
    $device->getPHID(),
    array(
      'force' => true,
    ));
}
