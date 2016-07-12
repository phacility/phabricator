<?php

echo pht('Moving Slowvote comments to transactions...')."\n";

$viewer = PhabricatorUser::getOmnipotentUser();

$table_xaction = new PhabricatorSlowvoteTransaction();
$table_comment = new PhabricatorSlowvoteTransactionComment();
$conn_w = $table_xaction->establishConnection('w');

$comments = new LiskRawMigrationIterator($conn_w, 'slowvote_comment');

$conn_w->openTransaction();

foreach ($comments as $comment) {
  $id = $comment['id'];
  $poll_id = $comment['pollID'];
  $author_phid = $comment['authorPHID'];
  $text = $comment['commentText'];
  $date_created = $comment['dateCreated'];
  $date_modified = $comment['dateModified'];

  echo pht('Migrating comment %d.', $id)."\n";

  $poll = id(new PhabricatorSlowvoteQuery())
    ->setViewer($viewer)
    ->withIDs(array($poll_id))
    ->executeOne();
  if (!$poll) {
    echo pht('No poll.')."\n";
    continue;
  }

  $user = id(new PhabricatorPeopleQuery())
    ->setViewer($viewer)
    ->withPHIDs(array($author_phid))
    ->executeOne();
  if (!$user) {
    echo pht('No user.')."\n";
    continue;
  }

  $comment_phid = PhabricatorPHID::generateNewPHID(
    PhabricatorPHIDConstants::PHID_TYPE_XCMT);
  $xaction_phid = PhabricatorPHID::generateNewPHID(
    PhabricatorApplicationTransactionTransactionPHIDType::TYPECONST,
    PhabricatorSlowvotePollPHIDType::TYPECONST);

  $content_source = PhabricatorContentSource::newForSource(
    PhabricatorOldWorldContentSource::SOURCECONST)->serialize();

  queryfx(
    $conn_w,
    'INSERT INTO %T (phid, transactionPHID, authorPHID, viewPolicy, editPolicy,
        commentVersion, content, contentSource, isDeleted,
        dateCreated, dateModified)
      VALUES (%s, %s, %s, %s, %s,
        %d, %s, %s, %d,
        %d, %d)',
    $table_comment->getTableName(),
    $comment_phid,
    $xaction_phid,
    $user->getPHID(),
    PhabricatorPolicies::POLICY_PUBLIC,
    $user->getPHID(),
    1,
    $text,
    $source,
    0,
    $date_created,
    $date_modified);

  queryfx(
    $conn_w,
    'INSERT INTO %T (phid, authorPHID, objectPHID, viewPolicy, editPolicy,
        commentPHID, commentVersion, transactionType, oldValue, newValue,
        contentSource, metadata, dateCreated, dateModified)
      VALUES (%s, %s, %s, %s, %s,
        %s, %d, %s, %s, %s,
        %s, %s, %d, %d)',
    $table_xaction->getTableName(),
    $xaction_phid,
    $user->getPHID(),
    $poll->getPHID(),
    PhabricatorPolicies::POLICY_PUBLIC,
    $user->getPHID(),
    $comment_phid,
    1,
    PhabricatorTransactions::TYPE_COMMENT,
    null,
    null,
    $source,
    '{}',
    $date_created,
    $date_modified);
}

$conn_w->saveTransaction();

echo pht('Done.')."\n";
