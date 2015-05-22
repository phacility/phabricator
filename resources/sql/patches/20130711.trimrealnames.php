<?php

$table = new PhabricatorUser();
$conn_w = $table->establishConnection('w');

echo pht('Trimming trailing whitespace from user real names...')."\n";
foreach (new LiskMigrationIterator($table) as $user) {
  $id = $user->getID();
  $real = $user->getRealName();
  $trim = rtrim($real);

  if ($trim == $real) {
    echo pht('User %d is already trim.', $id)."\n";
    continue;
  }

  echo pht("Trimming user %d from '%s' to '%s'.", $id, $real, $trim)."\n";
  qsprintf(
    $conn_w,
    'UPDATE %T SET realName = %s WHERE id = %d',
    $table->getTableName(),
    $real,
    $id);
}

echo pht('Done.')."\n";
