<?php

echo "Populating Questions with mail keys...\n";
$table = new PonderQuestion();
$table->openTransaction();

foreach (new LiskMigrationIterator($table) as $question) {
  $id = $question->getID();

  echo "Question {$id}: ";
  if (!$question->getMailKey()) {
    queryfx(
      $question->establishConnection('w'),
      'UPDATE %T SET mailKey = %s WHERE id = %d',
      $question->getTableName(),
      Filesystem::readRandomCharacters(20),
      $id);
    echo "Generated Key\n";
  } else {
    echo "-\n";
  }
}

$table->saveTransaction();
echo "Done.\n";
