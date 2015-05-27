<?php

echo pht('Populating pastes with mail keys...')."\n";

$table = new PhabricatorPaste();
$table->openTransaction();
$conn_w = $table->establishConnection('w');

foreach (new LiskMigrationIterator($table) as $paste) {
  $id = $paste->getID();

  echo "P{$id}: ";
  if (!$paste->getMailKey()) {
    queryfx(
      $conn_w,
      'UPDATE %T SET mailKey = %s WHERE id = %d',
      $paste->getTableName(),
      Filesystem::readRandomCharacters(20),
      $id);
    echo pht('Generated Key')."\n";
  } else {
    echo "-\n";
  }
}

$table->saveTransaction();
echo pht('Done.')."\n";
