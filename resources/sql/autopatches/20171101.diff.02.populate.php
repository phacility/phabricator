<?php

$table = new DifferentialRevision();
$conn = $table->establishConnection('w');
$diff_table = new DifferentialDiff();

foreach (new LiskMigrationIterator($table) as $revision) {
  $revision_id = $revision->getID();

  $diff_row = queryfx_one(
    $conn,
    'SELECT phid FROM %T WHERE revisionID = %d ORDER BY id DESC LIMIT 1',
    $diff_table->getTableName(),
    $revision_id);

  if ($diff_row) {
    queryfx(
      $conn,
      'UPDATE %T SET activeDiffPHID = %s WHERE id = %d',
      $table->getTableName(),
      $diff_row['phid'],
      $revision_id);
  }
}
