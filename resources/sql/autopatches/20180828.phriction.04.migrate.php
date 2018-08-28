<?php

// Update the PhrictionDocument and PhrictionContent tables to refer to one
// another by PHID instead of by ID.

$document_table = new PhrictionDocument();
$content_table = new PhrictionContent();

$conn = $document_table->establishConnection('w');

$document_iterator = new LiskRawMigrationIterator(
  $conn,
  $document_table->getTableName());
foreach ($document_iterator as $row) {
  $content_id = $row['contentID'];

  $content_row = queryfx_one(
    $conn,
    'SELECT phid, dateCreated FROM %T WHERE id = %d',
    $content_table->getTableName(),
    $content_id);

  if (!$content_row) {
    continue;
  }

  queryfx(
    $conn,
    'UPDATE %T SET contentPHID = %s, editedEpoch = %d WHERE id = %d',
    $document_table->getTableName(),
    $content_row['phid'],
    $content_row['dateCreated'],
    $row['id']);
}

$content_iterator = new LiskRawMigrationIterator(
  $conn,
  $content_table->getTableName());
foreach ($content_iterator as $row) {
  $document_id = $row['documentID'];

  $document_row = queryfx_one(
    $conn,
    'SELECT phid FROM %T WHERE id = %d',
    $document_table->getTableName(),
    $document_id);
  if (!$document_row) {
    continue;
  }

  queryfx(
    $conn,
    'UPDATE %T SET documentPHID = %s WHERE id = %d',
    $content_table->getTableName(),
    $document_row['phid'],
    $row['id']);
}
