<?php

echo pht('Populating Questions with mail keys...')."\n";
$table = new PonderQuestion();
$table->openTransaction();

foreach (new LiskMigrationIterator($table) as $question) {
  $id = $question->getID();

  echo pht('Question %d: ', $id);
  if (!$question->getMailKey()) {
    queryfx(
      $question->establishConnection('w'),
      'UPDATE %T SET mailKey = %s WHERE id = %d',
      $question->getTableName(),
      Filesystem::readRandomCharacters(20),
      $id);
    echo pht('Generated Key')."\n";
  } else {
    echo "-\n";
  }
}

$table->saveTransaction();
echo pht('Done.')."\n";
