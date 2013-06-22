<?php

$conn = id(new DifferentialRevision())->establishConnection('r');

echo "Assigning transaction PHIDs to DifferentialComments.\n";
foreach (new LiskRawMigrationIterator($conn, 'differential_comment') as $row) {
  $id = $row['id'];
  echo "Migrating comment #{$id}...\n";
  if ($row['phid']) {
    continue;
  }

  queryfx(
    $conn,
    'UPDATE %T SET phid = %s WHERE id = %d',
    'differential_comment',
    PhabricatorPHID::generateNewPHID(
      PhabricatorPHIDConstants::PHID_TYPE_XACT,
      PhabricatorPHIDConstants::PHID_TYPE_DREV),
    $id);
}

echo "Done.\n";
