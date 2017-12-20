<?php

$table = new PhabricatorRepositoryRefCursor();
$conn = $table->establishConnection('w');

$map = array();
foreach (new LiskMigrationIterator($table) as $ref) {
  $repository_phid = $ref->getRepositoryPHID();
  $ref_type = $ref->getRefType();
  $ref_hash = $ref->getRefNameHash();

  $ref_key = "{$repository_phid}/{$ref_type}/{$ref_hash}";

  if (!isset($map[$ref_key])) {
    $map[$ref_key] = array(
      'id' => $ref->getID(),
      'type' => $ref_type,
      'hash' => $ref_hash,
      'repositoryPHID' => $repository_phid,
      'positions' => array(),
    );
  }

  // NOTE: When this migration runs, the table will have "commitIdentifier" and
  // "isClosed" fields. Later, it won't. Since they'll be removed, we can't
  // rely on being able to access them via the object. Instead, run a separate
  // raw query to read them.

  $row = queryfx_one(
    $conn,
    'SELECT commitIdentifier, isClosed FROM %T WHERE id = %d',
    $ref->getTableName(),
    $ref->getID());

  $map[$ref_key]['positions'][] = array(
    'identifier' => $row['commitIdentifier'],
    'isClosed' => (int)$row['isClosed'],
  );
}

// Now, write all the position rows.
$position_table = new PhabricatorRepositoryRefPosition();
foreach ($map as $ref_key => $spec) {
  $id = $spec['id'];
  foreach ($spec['positions'] as $position) {
    queryfx(
      $conn,
      'INSERT IGNORE INTO %T (cursorID, commitIdentifier, isClosed)
        VALUES (%d, %s, %d)',
      $position_table->getTableName(),
      $id,
      $position['identifier'],
      $position['isClosed']);
  }
}

// Finally, delete all the redundant RefCursor rows (rows with the same name)
// so we can add proper unique keys in the next migration.
foreach ($map as $ref_key => $spec) {
  queryfx(
    $conn,
    'DELETE FROM %T WHERE refType = %s
      AND refNameHash = %s
      AND repositoryPHID = %s
      AND id != %d',
    $table->getTableName(),
    $spec['type'],
    $spec['hash'],
    $spec['repositoryPHID'],
    $spec['id']);
}
