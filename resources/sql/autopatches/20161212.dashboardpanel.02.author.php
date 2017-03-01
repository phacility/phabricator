<?php

// Set authorPHID on Dashboard Panels
//
$table = new PhabricatorDashboardPanel();
$conn_w = $table->establishConnection('w');

$txn_table = new PhabricatorDashboardPanelTransaction();
$txn_conn = $table->establishConnection('r');

echo pht("Building Dashboard Panel authorPHIDs...\n");

foreach (new LiskMigrationIterator($table) as $panel) {

  if ($panel->getAuthorPHID()) {
    continue;
  }

  $panel_row = queryfx_one(
    $txn_conn,
    'SELECT authorPHID FROM %T WHERE objectPHID = %s ORDER BY id ASC LIMIT 1',
    $txn_table->getTableName(),
    $panel->getPHID());

  if (!$panel_row) {
    $author_phid = id(new PhabricatorDashboardApplication())->getPHID();
  } else {
    $author_phid = $panel_row['authorPHID'];
  }

  queryfx(
    $conn_w,
    'UPDATE %T SET authorPHID = %s WHERE id = %d',
    $table->getTableName(),
    $author_phid,
    $panel->getID());
}

echo pht("Done\n");
