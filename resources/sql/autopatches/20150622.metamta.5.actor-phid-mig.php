<?php

$table = new PhabricatorMetaMTAMail();
$conn_w = $table->establishConnection('w');

echo pht('Assigning actorPHIDs to mails...')."\n";
foreach (new LiskMigrationIterator($table) as $mail) {
  $id = $mail->getID();

  echo pht('Updating mail %d...', $id)."\n";
  if ($mail->getActorPHID()) {
    continue;
  }

  $actor_phid = $mail->getFrom();
  if ($actor_phid === null) {
    continue;
  }

  queryfx(
    $conn_w,
    'UPDATE %T SET actorPHID = %s WHERE id = %d',
    $table->getTableName(),
    $actor_phid,
    $id);
}
echo pht('Done.')."\n";
