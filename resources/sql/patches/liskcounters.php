<?php

// Switch PhabricatorWorkerActiveTask from autoincrement IDs to counter IDs.
// Set the initial counter ID to be larger than any known task ID.

$active_table = new PhabricatorWorkerActiveTask();
$archive_table = new PhabricatorWorkerArchiveTask();

$conn_w = $active_table->establishConnection('w');

$active_auto = head(queryfx_one(
  $conn_w,
  'SELECT auto_increment FROM information_schema.tables
    WHERE table_name = %s
    AND table_schema = DATABASE()',
  $active_table->getTableName()));

$active_max = head(queryfx_one(
  $conn_w,
  'SELECT MAX(id) FROM %T',
  $active_table->getTableName()));

$archive_max = head(queryfx_one(
  $conn_w,
  'SELECT MAX(id) FROM %T',
  $archive_table->getTableName()));

$initial_counter = max((int)$active_auto, (int)$active_max, (int)$archive_max);

queryfx(
  $conn_w,
  'INSERT IGNORE INTO %T (counterName, counterValue)
    VALUES (%s, %d)',
  LiskDAO::COUNTER_TABLE_NAME,
  $active_table->getTableName(),
  $initial_counter + 1);

// Drop AUTO_INCREMENT from the ID column.
queryfx(
  $conn_w,
  'ALTER TABLE %T CHANGE id id INT UNSIGNED NOT NULL',
  $active_table->getTableName());
