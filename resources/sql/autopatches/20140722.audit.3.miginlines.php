<?php

$audit_table = new PhabricatorAuditTransaction();
$conn_w = $audit_table->establishConnection('w');
$conn_w->openTransaction();

$src_table = 'audit_inlinecomment';
$dst_table = 'audit_transaction_comment';

echo pht('Migrating Audit inline comments to new format...')."\n";

$content_source = PhabricatorContentSource::newForSource(
  PhabricatorContentSource::SOURCE_LEGACY,
  array())->serialize();

$rows = new LiskRawMigrationIterator($conn_w, $src_table);
foreach ($rows as $row) {
  $id = $row['id'];

  echo pht('Migrating inline #%d...', $id);

  if ($row['auditCommentID']) {
    $xaction_phid = PhabricatorPHID::generateNewPHID(
      PhabricatorApplicationTransactionTransactionPHIDType::TYPECONST,
      PhabricatorRepositoryCommitPHIDType::TYPECONST);
  } else {
    $xaction_phid = null;
  }

  $comment_phid = PhabricatorPHID::generateNewPHID(
    PhabricatorPHIDConstants::PHID_TYPE_XCMT,
    PhabricatorRepositoryCommitPHIDType::TYPECONST);

  queryfx(
    $conn_w,
    'INSERT IGNORE INTO %T
      (id, phid, transactionPHID, authorPHID, viewPolicy, editPolicy,
        commentVersion, content, contentSource, isDeleted,
        dateCreated, dateModified, commitPHID, pathID,
        isNewFile, lineNumber, lineLength, hasReplies, legacyCommentID)
      VALUES (%d, %s, %ns, %s, %s, %s,
        %d, %s, %s, %d,
        %d, %d, %s, %nd,
        %d, %d, %d, %d, %nd)',
    $dst_table,

    // id, phid, transactionPHID, authorPHID, viewPolicy, editPolicy
    $row['id'],
    $comment_phid,
    $xaction_phid,
    $row['authorPHID'],
    'public',
    $row['authorPHID'],

    // commentVersion, content, contentSource, isDeleted
    1,
    $row['content'],
    $content_source,
    0,

    // dateCreated, dateModified, commitPHID, pathID
    $row['dateCreated'],
    $row['dateModified'],
    $row['commitPHID'],
    $row['pathID'],

    // isNewFile, lineNumber, lineLength, hasReplies, legacyCommentID
    $row['isNewFile'],
    $row['lineNumber'],
    $row['lineLength'],
    0,
    $row['auditCommentID']);

}

$conn_w->saveTransaction();
echo pht('Done.')."\n";
