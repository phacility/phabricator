<?php

$table = new PhabricatorUser();
$conn_w = $table->establishConnection('w');

echo "Trimming trailing whitespace from user real names...\n";
foreach (new LiskMigrationIterator($table) as $user) {
  $id = $user->getID();
  $real = $user->getRealName();
  $trim = rtrim($real);

  if ($trim == $real) {
    echo "User {$id} is already trim.\n";
    continue;
  }

  echo "Trimming user {$id} from '{$real}' to '{$trim}'.\n";
  qsprintf(
    $conn_w,
    'UPDATE %T SET realName = %s WHERE id = %d',
    $table->getTableName(),
    $real,
    $id);
}

echo "Done.\n";
