<?php

echo "Migrating Releeph requests events to transactions...\n";

$table = new ReleephRequest();
$table->openTransaction();
$table->beginReadLocking();

foreach (new LiskMigrationIterator($table) as $rq) {
  printf("RQ%d:", $rq->getID());

  $intents_cursor = array();
  $last_pick_status = null;
  $last_commit_id = null;

  foreach ($rq->loadEvents() as $event) {
    $author = $event->getActorPHID();
    $details = $event->getDetails();

    $content_source = PhabricatorContentSource::newForSource(
      PhabricatorContentSource::SOURCE_UNKNOWN,
      array('ReleephRequestEventID' => $event->getID()));

    $xaction = id(new ReleephRequestTransaction())
      ->setObjectPHID($rq->getPHID())
      ->setAuthorPHID($author)
      ->setContentSource($content_source)
      ->setDateCreated($event->getDateCreated())
      ->setDateModified($event->getDateModified())
      ->setViewPolicy(PhabricatorPolicies::POLICY_PUBLIC)
      ->setEditPolicy($author);

    printf(" %s(#%d)", $event->getType(), $event->getID());

    switch ($event->getType()) {
      case ReleephRequestEvent::TYPE_COMMENT:
        $xaction
          ->setTransactionType(PhabricatorTransactions::TYPE_COMMENT)
          ->save(); // to generate a PHID
        $comment = id(new ReleephRequestTransactionComment())
          ->setAuthorPHID($author)
          ->setTransactionPHID($xaction->getPHID())
          ->setViewPolicy(PhabricatorPolicies::POLICY_PUBLIC)
          ->setEditPolicy($author)
          ->setCommentVersion(1)
          ->setContent($event->getComment())
          ->setContentSource($content_source)
          ->save();
        $xaction
          ->setCommentPHID($comment->getPHID())
          ->setCommentVersion(1);
        break;

      case ReleephRequestEvent::TYPE_STATUS:
        // Ignore STATUS events; these are legacy events that we no longer
        // support anyway!
        continue 2;

      case ReleephRequestEvent::TYPE_USER_INTENT:
        $old_intent = idx($intents_cursor, $author);
        $new_intent = $event->getDetail('newIntent');
        $xaction
          ->setTransactionType(ReleephRequestTransaction::TYPE_USER_INTENT)
          ->setMetadataValue('isAuthoritative', $event->getDetail('wasPusher'))
          ->setOldValue($old_intent)
          ->setNewValue($new_intent);
        $intents_cursor[$author] = $new_intent;
        break;

      case ReleephRequestEvent::TYPE_PICK_STATUS:
        $new_pick_status = $event->getDetail('newPickStatus');
        $xaction
          ->setTransactionType(ReleephRequestTransaction::TYPE_PICK_STATUS)
          ->setOldValue($last_pick_status)
          ->setNewValue($new_pick_status);
        $last_pick_status = $new_pick_status;
        break;

      case ReleephRequestEvent::TYPE_COMMIT:
        $new_commit_id = $event->getDetail('newCommitIdentifier');
        $xaction
          ->setTransactionType(ReleephRequestTransaction::TYPE_COMMIT)
          ->setMetadataValue('action', $event->getDetail('action'))
          ->setOldValue($last_commit_id)
          ->setNewValue($new_commit_id);
        $last_commit_id = $new_commit_id;
        break;

      case ReleephRequestEvent::TYPE_DISCOVERY:
        $xaction
          ->setTransactionType(ReleephRequestTransaction::TYPE_DISCOVERY)
          ->setNewValue($event->getDetail('newCommitPHID'));
        break;

      case ReleephRequestEvent::TYPE_CREATE:
        $xaction
          ->setTransactionType(ReleephRequestTransaction::TYPE_REQUEST)
          ->setOldValue(null)
          ->setNewValue($rq->getRequestCommitPHID());
        break;

      case ReleephRequestEvent::TYPE_MANUAL_ACTION:
        $xaction
          ->setTransactionType(
            ReleephRequestTransaction::TYPE_MANUAL_IN_BRANCH);
        switch ($event->getDetail('action')) {
          case 'pick':
            $xaction->setNewValue(1);
            break;

          case 'revert':
            $xaction->setNewValue(0);
            break;
        }
        break;

      default:
        throw new Exception(sprintf(
          "Unhandled ReleephRequestEvent type '%s' in RQ%d",
          $event->getType(),
          $rq->getID()));
    }

    $xaction->save();
  }
  echo("\n");
}

$table->endReadLocking();
$table->saveTransaction();
echo "Done.\n";
