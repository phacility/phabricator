<?php

$table = new AlmanacNetwork();
$conn = $table->establishConnection('w');

queryfx(
  $conn,
  'LOCK TABLES %T WRITE',
  $table->getTableName());

$seen = array();
foreach (new LiskMigrationIterator($table) as $network) {
  $name = $network->getName();

  // If this is the first copy of this row we've seen, mark it as seen and
  // move on.
  if (empty($seen[$name])) {
    $seen[$name] = 1;
    continue;
  }

  // Otherwise, rename this row.
  while (true) {
    $new_name = $name.'-'.$seen[$name];
    if (empty($seen[$new_name])) {
      $network->setName($new_name);
      try {
        $network->save();
        break;
      } catch (AphrontDuplicateKeyQueryException $ex) {
        // New name is a dupe of a network we haven't seen yet.
      }
    }
    $seen[$name]++;
  }
  $seen[$new_name] = 1;
}

queryfx(
  $conn,
  'ALTER TABLE %T ADD UNIQUE KEY `key_name` (name)',
  $table->getTableName());

queryfx(
  $conn,
  'UNLOCK TABLES');
