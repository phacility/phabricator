<?php

// Set authorPHID on Dashboards
//
$table = new PhabricatorDashboard();
$conn_w = $table->establishConnection('w');

$txn_table = new PhabricatorDashboardTransaction();
$txn_conn = $table->establishConnection('r');

echo pht("Building Dashboard authorPHIDs...\n");

foreach (new LiskMigrationIterator($table) as $dashboard) {

  if ($dashboard->getAuthorPHID()) {
    continue;
  }

  $author_row = queryfx_one(
    $txn_conn,
    'SELECT authorPHID FROM %T WHERE objectPHID = %s ORDER BY id ASC LIMIT 1',
    $txn_table->getTableName(),
    $dashboard->getPHID());

  if (!$author_row) {
    $author_phid = id(new PhabricatorDashboardApplication())->getPHID();
  } else {
    $author_phid = $author_row['authorPHID'];
  }

  queryfx(
    $conn_w,
    'UPDATE %T SET authorPHID = %s WHERE id = %d',
    $table->getTableName(),
    $author_phid,
    $dashboard->getID());
}

echo pht("Done\n");
