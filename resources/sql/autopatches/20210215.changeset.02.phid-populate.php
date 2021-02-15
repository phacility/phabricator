<?php

$phid_type = DifferentialChangesetPHIDType::TYPECONST;

$changeset_table = new DifferentialChangeset();

$conn = $changeset_table->establishConnection('w');
$table_name = $changeset_table->getTableName();

$iterator = new LiskRawMigrationIterator($conn, $table_name);
foreach ($iterator as $changeset_row) {
  $phid = $changeset_row['phid'];

  if (strlen($phid)) {
    continue;
  }

  $phid = PhabricatorPHID::generateNewPHID($phid_type);

  queryfx(
    $conn,
    'UPDATE %T SET phid = %s WHERE id = %d',
    $table_name,
    $phid,
    $changeset_row['id']);
}
