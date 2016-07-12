<?php

$conn_w = id(new DifferentialRevision())->establishConnection('w');
$rows = new LiskRawMigrationIterator($conn_w, 'differential_comment');

$content_source = PhabricatorContentSource::newForSource(
  PhabricatorOldWorldContentSource::SOURCECONST)->serialize();

echo pht('Migrating Differential comments to modern storage...')."\n";
foreach ($rows as $row) {
  $id = $row['id'];
  echo pht('Migrating comment %d...', $id)."\n";

  $revision = id(new DifferentialRevision())->load($row['revisionID']);
  if (!$revision) {
    echo pht('No revision, continuing.')."\n";
    continue;
  }

  $revision_phid = $revision->getPHID();

  $comments = queryfx_all(
    $conn_w,
    'SELECT * FROM %T WHERE legacyCommentID = %d',
    'differential_transaction_comment',
    $id);

  $main_comments = array();
  $inline_comments = array();

  foreach ($comments as $comment) {
    if ($comment['changesetID']) {
      $inline_comments[] = $comment;
    } else {
      $main_comments[] = $comment;
    }
  }

  $metadata = json_decode($row['metadata'], true);
  if (!is_array($metadata)) {
    $metadata = array();
  }

  $key_cc = 'added-ccs';
  $key_add_rev = 'added-reviewers';
  $key_rem_rev = 'removed-reviewers';
  $key_diff_id = 'diff-id';

  $xactions = array();

  // Build the main action transaction.
  switch ($row['action']) {
    case DifferentialAction::ACTION_COMMENT:
    case DifferentialAction::ACTION_ADDREVIEWERS:
    case DifferentialAction::ACTION_ADDCCS:
    case DifferentialAction::ACTION_UPDATE:
    case DifferentialTransaction::TYPE_INLINE:
      // These actions will have their transactions created by other rules.
      break;
    default:
      // Otherwise, this is a normal action (like an accept or reject).
      $xactions[] = array(
        'type' => DifferentialTransaction::TYPE_ACTION,
        'old' => null,
        'new' => $row['action'],
      );
      break;
  }

  // Build the diff update transaction, if one exists.
  $diff_id = idx($metadata, $key_diff_id);
  if (!is_scalar($diff_id)) {
    $diff_id = null;
  }

  if ($diff_id || $row['action'] == DifferentialAction::ACTION_UPDATE) {
    $xactions[] = array(
      'type' => DifferentialTransaction::TYPE_UPDATE,
      'old' => null,
      'new' => $diff_id,
    );
  }

  // Build the add/remove reviewers transaction, if one exists.
  $add_rev = idx($metadata, $key_add_rev, array());
  if (!is_array($add_rev)) {
    $add_rev = array();
  }
  $rem_rev = idx($metadata, $key_rem_rev, array());
  if (!is_array($rem_rev)) {
    $rem_rev = array();
  }

  if ($add_rev || $rem_rev) {
    $old = array();
    foreach ($rem_rev as $phid) {
      if (!is_scalar($phid)) {
        continue;
      }
      $old[$phid] = array(
        'src' => $revision_phid,
        'type' => DifferentialRevisionHasReviewerEdgeType::EDGECONST,
        'dst' => $phid,
      );
    }

    $new = array();
    foreach ($add_rev as $phid) {
      if (!is_scalar($phid)) {
        continue;
      }
      $new[$phid] = array(
        'src' => $revision_phid,
        'type' => DifferentialRevisionHasReviewerEdgeType::EDGECONST,
        'dst' => $phid,
      );
    }

    $xactions[] = array(
      'type' => PhabricatorTransactions::TYPE_EDGE,
      'old' => $old,
      'new' => $new,
      'meta' => array(
        'edge:type' => DifferentialRevisionHasReviewerEdgeType::EDGECONST,
      ),
    );
  }

  // Build the CC transaction, if one exists.
  $add_cc = idx($metadata, $key_cc, array());
  if (!is_array($add_cc)) {
    $add_cc = array();
  }

  if ($add_cc) {
    $xactions[] = array(
      'type' => PhabricatorTransactions::TYPE_SUBSCRIBERS,
      'old' => array(),
      'new' => array_fuse($add_cc),
    );
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
      'type' => DifferentialTransaction::TYPE_INLINE,
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
        DifferentialRevisionPHIDType::TYPECONST);
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
      'differential_transaction',

      // PHID, authorPHID, objectPHID
      $xaction_phid,
      (string)$row['authorPHID'],
      $revision_phid,

      // viewPolicy, editPolicy, commentPHID, commentVersion
      'public',
      (string)$row['authorPHID'],
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
