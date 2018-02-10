<?php

$conn = id(new DifferentialRevision())->establishConnection('w');
$src_table = 'differential_hunk';
$dst_table = 'differential_hunk_modern';

echo tsprintf(
  "%s\n",
  pht('Migrating old hunks...'));

foreach (new LiskRawMigrationIterator($conn, $src_table) as $row) {
  queryfx(
    $conn,
    'INSERT INTO %T
      (changesetID, oldOffset, oldLen, newOffset, newLen,
        dataType, dataEncoding, dataFormat, data,
        dateCreated, dateModified)
      VALUES
      (%d, %d, %d, %d, %d,
        %s, %s, %s, %s,
        %d, %d)',
    $dst_table,
    $row['changesetID'],
    $row['oldOffset'],
    $row['oldLen'],
    $row['newOffset'],
    $row['newLen'],
    DifferentialHunk::DATATYPE_TEXT,
    'utf8',
    DifferentialHunk::DATAFORMAT_RAW,
    // In rare cases, this could be NULL. See T12090.
    (string)$row['changes'],
    $row['dateCreated'],
    $row['dateModified']);
}

echo tsprintf(
  "%s\n",
  pht('Done.'));
