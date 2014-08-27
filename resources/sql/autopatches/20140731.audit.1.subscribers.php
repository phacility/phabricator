<?php

$table = new PhabricatorRepositoryAuditRequest();
$conn_w = $table->establishConnection('w');

echo "Migrating Audit subscribers to subscriptions...\n";
foreach (new LiskMigrationIterator($table) as $request) {
  $id = $request->getID();

  echo "Migrating auditor {$id}...\n";

  if ($request->getAuditStatus() != 'cc') {
    // This isn't a "subscriber", so skip it.
    continue;
  }

  queryfx(
    $conn_w,
    'INSERT IGNORE INTO %T (src, type, dst) VALUES (%s, %d, %s)',
    PhabricatorEdgeConfig::TABLE_NAME_EDGE,
    $request->getCommitPHID(),
    PhabricatorEdgeConfig::TYPE_OBJECT_HAS_SUBSCRIBER,
    $request->getAuditorPHID());


  // Wipe the row.
  $request->delete();
}

echo "Done.\n";
