<?php

// Switch PhabricatorWorkerActiveTask from auto-increment IDs to counter IDs.
// Set the initial counter ID to be larger than any known task ID.

$active_table = new PhabricatorWorkerActiveTask();
$archive_table = new PhabricatorWorkerArchiveTask();

$old_table = 'worker_task';

$conn_w = $active_table->establishConnection('w');

$active_auto = head(queryfx_one(
  $conn_w,
  'SELECT auto_increment FROM information_schema.tables
    WHERE table_name = %s
    AND table_schema = DATABASE()',
  $old_table));

$active_max = head(queryfx_one(
  $conn_w,
  'SELECT MAX(id) FROM %T',
  $old_table));

$archive_max = head(queryfx_one(
  $conn_w,
  'SELECT MAX(id) FROM %T',
  $archive_table->getTableName()));

$initial_counter = max((int)$active_auto, (int)$active_max, (int)$archive_max);

queryfx(
  $conn_w,
  'INSERT INTO %T (counterName, counterValue)
    VALUES (%s, %d)
    ON DUPLICATE KEY UPDATE counterValue = %d',
  LiskDAO::COUNTER_TABLE_NAME,
  $old_table,
  $initial_counter + 1,
  $initial_counter + 1);
