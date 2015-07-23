<?php

$table = new ManiphestTransaction();
$conn_w = $table->establishConnection('w');

echo pht(
  "Converting Maniphest CC transactions to modern ".
  "subscriber transactions...\n");
foreach (new LiskMigrationIterator($table) as $txn) {
  // ManiphestTransaction::TYPE_CCS
  if ($txn->getTransactionType() == 'ccs') {
    queryfx(
      $conn_w,
      'UPDATE %T SET transactionType = %s WHERE id = %d',
      $table->getTableName(),
      PhabricatorTransactions::TYPE_SUBSCRIBERS,
      $txn->getID());
  }
}

echo pht('Done.')."\n";
