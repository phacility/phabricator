<?php

// See T13072. Turn the old "process a command" transaction into modular
// transactions that each handle one particular type of command.

$xactions_table = new HarbormasterBuildTransaction();
$xactions_conn = $xactions_table->establishConnection('w');
$row_iterator = new LiskRawMigrationIterator(
  $xactions_conn,
  $xactions_table->getTableName());

$map = array(
  '"pause"' => 'message/pause',
  '"abort"' => 'message/abort',
  '"resume"' => 'message/resume',
  '"restart"' => 'message/restart',
);

foreach ($row_iterator as $row) {
  if ($row['transactionType'] !== 'harbormaster:build:command') {
    continue;
  }

  $raw_value = $row['newValue'];

  if (isset($map[$raw_value])) {
    queryfx(
      $xactions_conn,
      'UPDATE %R SET transactionType = %s WHERE id = %d',
      $xactions_table,
      $map[$raw_value],
      $row['id']);
  }
}
