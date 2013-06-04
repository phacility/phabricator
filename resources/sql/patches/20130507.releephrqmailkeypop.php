<?php

echo "Populating Releeph requests with mail keys...\n";

$table = new ReleephRequest();
$table->openTransaction();

// From ponder-mailkey-populate.php...
foreach (new LiskMigrationIterator($table) as $rq) {
  $id = $rq->getID();

  echo "RQ{$id}: ";
  if (!$rq->getMailKey()) {
    queryfx(
      $rq->establishConnection('w'),
      'UPDATE %T SET mailKey = %s WHERE id = %d',
      $rq->getTableName(),
      Filesystem::readRandomCharacters(20),
      $id);
    echo("Generated Key\n");
  } else {
    echo "-\n";
  }
}

$table->saveTransaction();
echo "Done.\n";
