<?php

echo pht(
  "Migrating data from conpherence transactions to conpherence 'cache'...\n");

$table = new ConpherenceThread();
$table->openTransaction();
$conn_w = $table->establishConnection('w');

$participant_table = new ConpherenceParticipant();

$conpherences = new LiskMigrationIterator($table);
foreach ($conpherences as $conpherence) {
  echo pht('Migrating conpherence #%d', $conpherence->getID())."\n";

  $participants = id(new ConpherenceParticipant())
    ->loadAllWhere('conpherencePHID = %s', $conpherence->getPHID());

  $transactions = id(new ConpherenceTransaction())
    ->loadAllWhere('objectPHID = %s', $conpherence->getPHID());

  $participation_hash = mgroup($participants, 'getBehindTransactionPHID');

  $message_count = 0;
  $participants_to_cache = array();
  foreach ($transactions as $transaction) {
    $participants_to_cache[] = $transaction->getAuthorPHID();
    if ($transaction->getTransactionType() ==
      PhabricatorTransactions::TYPE_COMMENT) {
      $message_count++;
    }
    $participants_to_update = idx(
      $participation_hash,
      $transaction->getPHID(),
      array());
    if ($participants_to_update) {
      queryfx(
        $conn_w,
        'UPDATE %T SET seenMessageCount = %d '.
        'WHERE conpherencePHID = %s AND participantPHID IN (%Ls)',
        $participant_table->getTableName(),
        $message_count,
        $conpherence->getPHID(),
        mpull($participants_to_update, 'getParticipantPHID'));
    }
  }

  $participants_to_cache = array_slice(
    array_unique(array_reverse($participants_to_cache)),
    0,
    10);
  queryfx(
    $conn_w,
    'UPDATE %T '.
    'SET recentParticipantPHIDs = %s, '.
    'messageCount = %d '.
    'WHERE phid = %s',
    $table->getTableName(),
    json_encode($participants_to_cache),
    $message_count,
    $conpherence->getPHID());
}

$table->saveTransaction();
echo "\n".pht('Done.')."\n";
