<?php

$table = new PhabricatorOwnersPackage();

foreach (new LiskMigrationIterator($table) as $package) {
  PhabricatorSearchWorker::queueDocumentForIndexing(
    $package->getPHID(),
    array(
      'force' => true,
    ));
}
