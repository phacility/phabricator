<?php

$task_table = new ManiphestTask();
$conn_w = $task_table->establishConnection('w');

$rows = new LiskRawMigrationIterator($conn_w, 'maniphest_transaction');
$conn_w->openTransaction();

// NOTE: These were the correct table names at the time of this patch.
$xaction_table_name = 'maniphest_transactionpro';
$comment_table_name = 'maniphest_transaction_comment';

foreach ($rows as $row) {
  $row_id = $row['id'];
  $task_id = $row['taskID'];

  echo pht('Migrating row %d (%s)...', $row_id, "T{$task_id}")."\n";

  $task_row = queryfx_one(
    $conn_w,
    'SELECT phid FROM %T WHERE id = %d',
    $task_table->getTableName(),
    $task_id);
  if (!$task_row) {
    echo pht('Skipping, no such task.')."\n";
    continue;
  }

  $task_phid = $task_row['phid'];

  $has_comment = strlen(trim($row['comments']));

  $xaction_type = $row['transactionType'];
  $xaction_old = $row['oldValue'];
  $xaction_new = $row['newValue'];
  $xaction_source = idx($row, 'contentSource', '');
  $xaction_meta = $row['metadata'];

  // Convert "aux" (auxiliary field) transactions to proper CustomField
  // transactions. The formats are very similar, except that the type constant
  // is different and the auxiliary key should be prefixed.
  if ($xaction_type == 'aux') {
    $xaction_meta = @json_decode($xaction_meta, true);
    $xaction_meta = nonempty($xaction_meta, array());

    $xaction_type = PhabricatorTransactions::TYPE_CUSTOMFIELD;

    $aux_key = idx($xaction_meta, 'aux:key');
    if (!preg_match('/^std:maniphest:/', $aux_key)) {
      $aux_key = 'std:maniphest:'.$aux_key;
    }

    $xaction_meta = array(
      'customfield:key' => $aux_key,
    );

    $xaction_meta = json_encode($xaction_meta);
  }

  // If this transaction did something other than just leaving a comment,
  // insert a new transaction for that action. If there was a comment (or
  // a comment in addition to an action) we'll insert that below.
  if ($row['transactionType'] != 'comment') {
    $xaction_phid = PhabricatorPHID::generateNewPHID(
      PhabricatorApplicationTransactionTransactionPHIDType::TYPECONST,
      ManiphestTaskPHIDType::TYPECONST);

    queryfx(
      $conn_w,
      'INSERT INTO %T (phid, authorPHID, objectPHID, viewPolicy, editPolicy,
          commentPHID, commentVersion, transactionType, oldValue, newValue,
          contentSource, metadata, dateCreated, dateModified)
        VALUES (%s, %s, %s, %s, %s, %s, %d, %s, %ns, %ns, %s, %s, %d, %d)',
      $xaction_table_name,
      $xaction_phid,
      $row['authorPHID'],
      $task_phid,
      'public',
      $row['authorPHID'],
      null,
      0,
      $xaction_type,
      $xaction_old,
      $xaction_new,
      $xaction_source,
      $xaction_meta,
      $row['dateCreated'],
      $row['dateModified']);
  }

  // Now, if the old transaction has a comment, we insert an explicit new
  // transaction for it.
  if ($has_comment) {
    $comment_phid = PhabricatorPHID::generateNewPHID(
      PhabricatorPHIDConstants::PHID_TYPE_XCMT,
      ManiphestTaskPHIDType::TYPECONST);
    $comment_version = 1;

    $comment_xaction_phid = PhabricatorPHID::generateNewPHID(
      PhabricatorApplicationTransactionTransactionPHIDType::TYPECONST,
      ManiphestTaskPHIDType::TYPECONST);

    // Insert the comment data.
    queryfx(
      $conn_w,
      'INSERT INTO %T (phid, transactionPHID, authorPHID, viewPolicy,
          editPolicy, commentVersion, content, contentSource, isDeleted,
          dateCreated, dateModified)
        VALUES (%s, %s, %s, %s, %s, %d, %s, %s, %d, %d, %d)',
      $comment_table_name,
      $comment_phid,
      $comment_xaction_phid,
      $row['authorPHID'],
      'public',
      $row['authorPHID'],
      $comment_version,
      $row['comments'],
      $xaction_source,
      0,
      $row['dateCreated'],
      $row['dateModified']);

    queryfx(
      $conn_w,
      'INSERT INTO %T (phid, authorPHID, objectPHID, viewPolicy, editPolicy,
          commentPHID, commentVersion, transactionType, oldValue, newValue,
          contentSource, metadata, dateCreated, dateModified)
        VALUES (%s, %s, %s, %s, %s, %s, %d, %s, %ns, %ns, %s, %s, %d, %d)',
      $xaction_table_name,
      $comment_xaction_phid,
      $row['authorPHID'],
      $task_phid,
      'public',
      $row['authorPHID'],
      $comment_phid,
      $comment_version,
      PhabricatorTransactions::TYPE_COMMENT,
      $xaction_old,
      $xaction_new,
      $xaction_source,
      $xaction_meta,
      $row['dateCreated'],
      $row['dateModified']);
  }
}

$conn_w->saveTransaction();
echo pht('Done.')."\n";
