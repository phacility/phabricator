<?php

$table = new PhabricatorProjectColumn();
$conn_w = $table->establishConnection('w');

foreach (new LiskMigrationIterator($table) as $column) {
  $id = $column->getID();

  echo pht('Adjusting column %d...', $id)."\n";
  if ($column->getSequence() == 0) {

    $properties = $column->getProperties();
    $properties['isDefault'] = true;

    queryfx(
      $conn_w,
      'UPDATE %T SET properties = %s WHERE id = %d',
      $table->getTableName(),
      json_encode($properties),
      $id);
  }
}

echo pht('Done.')."\n";
