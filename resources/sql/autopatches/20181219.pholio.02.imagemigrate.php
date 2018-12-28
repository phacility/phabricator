<?php

// Old images used a "mockID" instead of a "mockPHID" to reference mocks.
// Set the "mockPHID" column to the value that corresponds to the "mockID".

$image = new PholioImage();
$mock = new PholioMock();

$conn = $image->establishConnection('w');
$iterator = new LiskRawMigrationIterator($conn, $image->getTableName());

foreach ($iterator as $image_row) {
  if ($image_row['mockPHID']) {
    continue;
  }

  $mock_id = $image_row['mockID'];

  $mock_row = queryfx_one(
    $conn,
    'SELECT phid FROM %R WHERE id = %d',
    $mock,
    $mock_id);

  if (!$mock_row) {
    continue;
  }

  queryfx(
    $conn,
    'UPDATE %R SET mockPHID = %s WHERE id = %d',
    $image,
    $mock_row['phid'],
    $image_row['id']);
}
