<?php

$table = new DifferentialRevision();
$conn_w = $table->establishConnection('w');

echo "Migrating revisions";
do {
  $revisions = id(new DifferentialRevision())
    ->loadAllWhere('branchName IS NULL LIMIT 1000');

  foreach ($revisions as $revision) {
    echo ".";

    $diff = $revision->loadActiveDiff();
    if (!$diff) {
      continue;
    }

    $branch_name = $diff->getBranch();
    $arc_project_phid = $diff->getArcanistProjectPHID();

    queryfx(
      $conn_w,
      'UPDATE %T SET branchName = %s, arcanistProjectPHID = %s WHERE id = %d',
      $table->getTableName(),
      $branch_name,
      $arc_project_phid,
      $revision->getID());
  }
} while (count($revisions) == 1000);
echo "\nDone.\n";
