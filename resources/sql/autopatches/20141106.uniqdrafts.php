<?php

// Destroy duplicate drafts before storage adjustment adds a unique key to this
// table. See T1191. We retain the newest draft.

// (We can't easily do this in a single SQL statement because MySQL won't let us
// modify a table that's joined in a subquery.)

$table = new DifferentialDraft();
$conn_w = $table->establishConnection('w');

$duplicates = queryfx_all(
  $conn_w,
  'SELECT DISTINCT u.id id FROM %T u
    JOIN %T v
      ON u.objectPHID = v.objectPHID
      AND u.authorPHID = v.authorPHID
      AND u.draftKey = v.draftKey
      AND u.id < v.id',
  $table->getTableName(),
  $table->getTableName());

$duplicates = ipull($duplicates, 'id');
foreach (PhabricatorLiskDAO::chunkSQL($duplicates) as $chunk) {
  queryfx(
    $conn_w,
    'DELETE FROM %T WHERE id IN (%Q)',
    $table->getTableName(),
    $chunk);
}
