<?php

echo pht('Adding names to Drydock blueprints.')."\n";

$table = new DrydockBlueprint();
$conn_w = $table->establishConnection('w');
$iterator = new LiskMigrationIterator($table);
foreach ($iterator as $blueprint) {
  $id = $blueprint->getID();

  echo pht('Populating blueprint %d...', $id)."\n";

  if (!strlen($blueprint->getBlueprintName())) {
    queryfx(
      $conn_w,
      'UPDATE %T SET blueprintName = %s WHERE id = %d',
      $table->getTableName(),
      pht('Blueprint %s', $id),
      $id);
  }
}

echo pht('Done.')."\n";
