<?php

$conn_w = id(new PhabricatorAuditTransaction())->establishConnection('w');
$rows = new LiskRawMigrationIterator($conn_w, 'audit_comment');

$content_source = PhabricatorContentSource::newForSource(
  PhabricatorContentSource::SOURCE_LEGACY,
  array())->serialize();

echo pht('Migrating Audit comments to modern storage...')."\n";
foreach ($rows as $row) {
  $id = $row['id'];
  echo pht('Migrating comment %d...', $id)."\n";

  $comments = queryfx_all(
    $conn_w,
    'SELECT * FROM %T WHERE legacyCommentID = %d',
    'audit_transaction_comment',
    $id);

  $main_comments = array();
  $inline_comments = array();

  foreach ($comments as $comment) {
    if ($comment['pathID']) {
      $inline_comments[] = $comment;
    } else {
      $main_comments[] = $comment;
    }
  }

  $metadata = json_decode($row['metadata'], true);
  if (!is_array($metadata)) {
    $metadata = array();
  }

  $xactions = array();

  // Build the main action transaction.
  switch ($row['action']) {
    case PhabricatorAuditActionConstants::ADD_AUDITORS:
      $phids = idx($metadata, 'added-auditors', array());
      $xactions[] = array(
        'type' => $row['action'],
        'old' => null,
        'new' => array_fuse($phids),
      );
      break;
    case PhabricatorAuditActionConstants::ADD_CCS:
      $phids = idx($metadata, 'added-ccs', array());
      $xactions[] = array(
        'type' => $row['action'],
        'old' => null,
        'new' => array_fuse($phids),
      );
      break;
    case PhabricatorAuditActionConstants::COMMENT:
    case PhabricatorAuditActionConstants::INLINE:
      // These actions will have their transactions created by other rules.
      break;
    default:
      // Otherwise, this is an accept/concern/etc action.
      $xactions[] = array(
        'type' => PhabricatorAuditActionConstants::ACTION,
        'old' => null,
        'new' => $row['action'],
      );
      break;
  }


  // Build the main comment transaction.
  foreach ($main_comments as $main) {
    $xactions[] = array(
      'type' => PhabricatorTransactions::TYPE_COMMENT,
      'old' => null,
      'new' => null,
      'phid' => $main['transactionPHID'],
      'comment' => $main,
    );
  }

  // Build inline comment transactions.
  foreach ($inline_comments as $inline) {
    $xactions[] = array(
      'type' => PhabricatorAuditActionConstants::INLINE,
      'old' => null,
      'new' => null,
      'phid' => $inline['transactionPHID'],
      'comment' => $inline,
    );
  }

  foreach ($xactions as $xaction) {
    // Generate a new PHID, if we don't already have one from the comment
    // table. We pregenerated into the comment table to make this a little
    // easier, so we only need to write to one table.
    $xaction_phid = idx($xaction, 'phid');
    if (!$xaction_phid) {
      $xaction_phid = PhabricatorPHID::generateNewPHID(
        PhabricatorApplicationTransactionTransactionPHIDType::TYPECONST,
        PhabricatorRepositoryCommitPHIDType::TYPECONST);
    }
    unset($xaction['phid']);

    $comment_phid = null;
    $comment_version = 0;
    if (idx($xaction, 'comment')) {
      $comment_phid = $xaction['comment']['phid'];
      $comment_version = 1;
    }

    $old = idx($xaction, 'old');
    $new = idx($xaction, 'new');
    $meta = idx($xaction, 'meta', array());

    queryfx(
      $conn_w,
      'INSERT INTO %T (phid, authorPHID, objectPHID, viewPolicy, editPolicy,
          commentPHID, commentVersion, transactionType, oldValue, newValue,
          contentSource, metadata, dateCreated, dateModified)
        VALUES (%s, %s, %s, %s, %s, %ns, %d, %s, %ns, %ns, %s, %s, %d, %d)',
      'audit_transaction',

      // PHID, authorPHID, objectPHID
      $xaction_phid,
      $row['actorPHID'],
      $row['targetPHID'],

      // viewPolicy, editPolicy, commentPHID, commentVersion
      'public',
      $row['actorPHID'],
      $comment_phid,
      $comment_version,

      // transactionType, oldValue, newValue, contentSource, metadata
      $xaction['type'],
      json_encode($old),
      json_encode($new),
      $content_source,
      json_encode($meta),

      // dates
      $row['dateCreated'],
      $row['dateModified']);
  }

}

echo pht('Done.')."\n";
