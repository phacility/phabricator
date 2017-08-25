<?php

final class DifferentialRevisionInlineTransaction
  extends PhabricatorModularTransactionType {

  // NOTE: This class is NOT an actual Differential modular transaction type!
  // It does not extend "DifferentialRevisionTransactionType". Some day it
  // should, but for now it's just reducing the amount of hackiness around
  // supporting inline comments in the "transaction.search" Conduit API method.

  const TRANSACTIONTYPE = 'internal.pretend-inline';

  public function getTransactionTypeForConduit($xaction) {
    return 'inline';
  }

  public function loadTransactionTypeConduitData(array $xactions) {
    $viewer = $this->getViewer();

    $changeset_ids = array();
    foreach ($xactions as $xaction) {
      $changeset_ids[] = $xaction->getComment()->getChangesetID();
    }

    $changesets = id(new DifferentialChangesetQuery())
      ->setViewer($viewer)
      ->withIDs($changeset_ids)
      ->execute();

    $changesets = mpull($changesets, null, 'getID');

    return $changesets;
  }

  public function getFieldValuesForConduit($object, $data) {
    $comment = $object->getComment();

    $changeset = $data[$comment->getChangesetID()];
    $diff = $changeset->getDiff();

    return array(
      'diff' => array(
        'id' => (int)$diff->getID(),
        'phid' => $diff->getPHID(),
      ),
      'path' => $changeset->getDisplayFilename(),
      'line' => (int)$comment->getLineNumber(),
      'length' => (int)($comment->getLineLength() + 1),
      'replyToCommentPHID' => $comment->getReplyToCommentPHID(),
    );
  }

}
