<?php

echo "Adding names to Drydock blueprints.\n";

$table = new DrydockBlueprint();
$conn_w = $table->establishConnection('w');
$iterator = new LiskMigrationIterator($table);
foreach ($iterator as $blueprint) {
  $id = $blueprint->getID();

  echo "Populating blueprint {$id}...\n";

  if (!strlen($blueprint->getBlueprintName())) {
    queryfx(
      $conn_w,
      'UPDATE %T SET blueprintName = %s WHERE id = %d',
      $table->getTableName(),
      pht('Blueprint %s', $id),
      $id);
  }
}

echo "Done.\n";
