<?php

$table_db = new PhabricatorDashboard();

foreach (new LiskMigrationIterator($table_db) as $dashboard) {
  PhabricatorSearchWorker::queueDocumentForIndexing(
    $dashboard->getPHID(),
    array(
      'force' => true,
    ));
}

$table_dbp = new PhabricatorDashboardPanel();

foreach (new LiskMigrationIterator($table_dbp) as $panel) {
  PhabricatorSearchWorker::queueDocumentForIndexing(
    $panel->getPHID(),
    array(
      'force' => true,
    ));
}
