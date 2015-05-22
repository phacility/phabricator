<?php

$qtable = new PonderQuestionTransaction();
$atable = new PonderAnswerTransaction();

$conn_w = $qtable->establishConnection('w');
$conn_w->openTransaction();

echo pht('Migrating Ponder comments to %s...', 'ApplicationTransactions')."\n";

$rows = new LiskRawMigrationIterator($conn_w, 'ponder_comment');
foreach ($rows as $row) {

  $id = $row['id'];
  echo pht('Migrating %d...', $id)."\n";

  $type = phid_get_type($row['targetPHID']);
  switch ($type) {
    case PonderQuestionPHIDType::TYPECONST:
      $table_obj = $qtable;
      $comment_obj = new PonderQuestionTransactionComment();
      break;
    case PonderAnswerPHIDType::TYPECONST:
      $table_obj = $atable;
      $comment_obj = new PonderAnswerTransactionComment();
      break;
  }

  $comment_phid = PhabricatorPHID::generateNewPHID(
    PhabricatorApplicationTransactionTransactionPHIDType::TYPECONST,
    $type);

  $xaction_phid = PhabricatorPHID::generateNewPHID(
    PhabricatorApplicationTransactionTransactionPHIDType::TYPECONST,
    $type);

  queryfx(
    $conn_w,
    'INSERT INTO %T (phid, transactionPHID, authorPHID, viewPolicy, editPolicy,
        commentVersion, content, contentSource, isDeleted, dateCreated,
        dateModified)
      VALUES (%s, %s, %s, %s, %s, %d, %s, %s, %d, %d, %d)',
    $comment_obj->getTableName(),
    $comment_phid,
    $xaction_phid,
    $row['authorPHID'],
    'public',
    $row['authorPHID'],
    1,
    $row['content'],
    PhabricatorContentSource::newForSource(
      PhabricatorContentSource::SOURCE_LEGACY,
      array())->serialize(),
    0,
    $row['dateCreated'],
    $row['dateModified']);

  queryfx(
    $conn_w,
    'INSERT INTO %T (phid, authorPHID, objectPHID, viewPolicy, editPolicy,
        commentPHID, commentVersion, transactionType, oldValue, newValue,
        contentSource, metadata, dateCreated, dateModified)
      VALUES (%s, %s, %s, %s, %s, %s, %d, %s, %ns, %ns, %s, %s, %d, %d)',
    $table_obj->getTableName(),
    $xaction_phid,
    $row['authorPHID'],
    $row['targetPHID'],
    'public',
    $row['authorPHID'],
    $comment_phid,
    1,
    PhabricatorTransactions::TYPE_COMMENT,
    'null',
    'null',
    PhabricatorContentSource::newForSource(
      PhabricatorContentSource::SOURCE_LEGACY,
      array())->serialize(),
    '[]',
    $row['dateCreated'],
    $row['dateModified']);

}

$conn_w->saveTransaction();

echo pht('Done.')."\n";
