<?php

// Populate the "maxVersion" column by copying the maximum "version" from the
// content table.

$document_table = new PhrictionDocument();
$content_table = new PhrictionContent();

$conn = $document_table->establishConnection('w');

$iterator = new LiskRawMigrationIterator(
  $conn,
  $document_table->getTableName());
foreach ($iterator as $row) {
  $content = queryfx_one(
    $conn,
    'SELECT MAX(version) max FROM %T WHERE documentPHID = %s',
    $content_table->getTableName(),
    $row['phid']);
  if (!$content) {
    continue;
  }

  queryfx(
    $conn,
    'UPDATE %T SET maxVersion = %d WHERE id = %d',
    $document_table->getTableName(),
    $content['max'],
    $row['id']);
}
