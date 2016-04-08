<?php

$table = new PhabricatorBadgesBadge();

foreach (new LiskMigrationIterator($table) as $badge) {
  PhabricatorSearchWorker::queueDocumentForIndexing(
    $badge->getPHID(),
    array(
      'force' => true,
    ));
}
