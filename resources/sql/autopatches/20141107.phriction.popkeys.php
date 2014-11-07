<?php

$table = new PhrictionDocument();
$conn_w = $table->establishConnection('w');

echo "Populating Phriction mailkeys.\n";

foreach (new LiskMigrationIterator($table) as $doc) {
  $id = $doc->getID();

  $key = $doc->getMailKey();
  if ((strlen($key) == 20) && (strpos($key, "\0") === false)) {
    // To be valid, keys must have length 20 and not contain any null bytes.
    // See T6487.
    echo "Document has valid mailkey.\n";
    continue;
  } else {
    echo "Populating mailkey for document {$id}...\n";
    $mail_key = Filesystem::readRandomCharacters(20);
    queryfx(
      $conn_w,
      'UPDATE %T SET mailKey = %s WHERE id = %d',
      $table->getTableName(),
      $mail_key,
      $id);
  }
}

echo "Done.\n";
