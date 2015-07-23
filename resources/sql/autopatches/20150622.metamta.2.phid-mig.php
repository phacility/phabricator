<?php

$table = new PhabricatorMetaMTAMail();
$conn_w = $table->establishConnection('w');

echo pht('Assigning PHIDs to mails...')."\n";
foreach (new LiskMigrationIterator($table) as $mail) {
  $id = $mail->getID();

  echo pht('Updating mail %d...', $id)."\n";
  if ($mail->getPHID()) {
    continue;
  }

  queryfx(
    $conn_w,
    'UPDATE %T SET phid = %s WHERE id = %d',
    $table->getTableName(),
    $table->generatePHID(),
    $id);
}
echo pht('Done.')."\n";
