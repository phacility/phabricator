<?php

final class DifferentialTransactionEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhabricatorTransactions::TYPE_COMMENT;
    $types[] = PhabricatorTransactions::TYPE_EDGE;
    $types[] = PhabricatorTransactions::TYPE_VIEW_POLICY;
    $types[] = PhabricatorTransactions::TYPE_EDIT_POLICY;

    $types[] = DifferentialTransaction::TYPE_ACTION;
    $types[] = DifferentialTransaction::TYPE_INLINE;

/*

    $types[] = DifferentialTransaction::TYPE_UPDATE;
*/

    return $types;
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorTransactions::TYPE_VIEW_POLICY:
        return $object->getViewPolicy();
      case PhabricatorTransactions::TYPE_EDIT_POLICY:
        return $object->getEditPolicy();
      case DifferentialTransaction::TYPE_ACTION:
        return null;
      case DifferentialTransaction::TYPE_INLINE:
        return null;
    }

    return parent::getCustomTransactionOldValue($object, $xaction);
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorTransactions::TYPE_VIEW_POLICY:
      case PhabricatorTransactions::TYPE_EDIT_POLICY:
      case DifferentialTransaction::TYPE_ACTION:
        return $xaction->getNewValue();
      case DifferentialTransaction::TYPE_INLINE:
        return null;
    }

    return parent::getCustomTransactionNewValue($object, $xaction);
  }

  protected function transactionHasEffect(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case DifferentialTransaction::TYPE_INLINE:
        return $xaction->hasComment();
      case DifferentialTransaction::TYPE_ACTION:
        $status_closed = ArcanistDifferentialRevisionStatus::CLOSED;
        $status_abandoned = ArcanistDifferentialRevisionStatus::ABANDONED;
        $status_review = ArcanistDifferentialRevisionStatus::NEEDS_REVIEW;
        $status_revision = ArcanistDifferentialRevisionStatus::NEEDS_REVISION;

        switch ($xaction->getNewValue()) {
          case DifferentialAction::ACTION_CLOSE:
            return ($object->getStatus() != $status_closed);
          case DifferentialAction::ACTION_ABANDON:
            return ($object->getStatus() != $status_abandoned);
          case DifferentialAction::ACTION_RECLAIM:
            return ($object->getStatus() == $status_abandoned);
          case DifferentialAction::ACTION_REOPEN:
            return ($object->getStatus() == $status_closed);
          case DifferentialAction::ACTION_RETHINK:
            return ($object->getStatus() != $status_revision);
          case DifferentialAction::ACTION_REQUEST:
            return ($object->getStatus() != $status_review);
          case DifferentialAction::ACTION_RESIGN:
            $actor_phid = $this->getActor()->getPHID();
            foreach ($object->getReviewerStatus() as $reviewer) {
              if ($reviewer->getReviewerPHID() == $actor_phid) {
                return true;
              }
            }
            return false;
        }
    }

    return parent::transactionHasEffect($object, $xaction);
  }


  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorTransactions::TYPE_VIEW_POLICY:
        $object->setViewPolicy($xaction->getNewValue());
        return;
      case PhabricatorTransactions::TYPE_EDIT_POLICY:
        $object->setEditPolicy($xaction->getNewValue());
        return;
      case PhabricatorTransactions::TYPE_SUBSCRIBERS:
      case PhabricatorTransactions::TYPE_COMMENT:
      case DifferentialTransaction::TYPE_INLINE:
        return;
      case PhabricatorTransactions::TYPE_EDGE:
        // TODO: When removing reviewers, we may be able to move the revision
        // to "Accepted".
        return;
      case DifferentialTransaction::TYPE_ACTION:
        $status_review = ArcanistDifferentialRevisionStatus::NEEDS_REVIEW;
        $status_revision = ArcanistDifferentialRevisionStatus::NEEDS_REVISION;

        switch ($xaction->getNewValue()) {
          case DifferentialAction::ACTION_RESIGN:
            // TODO: Update review status?
            break;
          case DifferentialAction::ACTION_ABANDON:
            $object->setStatus(ArcanistDifferentialRevisionStatus::ABANDONED);
            break;
          case DifferentialAction::ACTION_RETHINK:
            $object->setStatus($status_revision);
            break;
          case DifferentialAction::ACTION_RECLAIM:
            $object->setStatus($status_review);
            // TODO: Update review status?
            break;
          case DifferentialAction::ACTION_REOPEN:
            $object->setStatus($status_review);
            // TODO: Update review status?
            break;
          case DifferentialAction::ACTION_REQUEST:
            $object->setStatus($status_review);
            // TODO: Update review status?
            break;
          case DifferentialAction::ACTION_CLOSE:
            $object->setStatus(ArcanistDifferentialRevisionStatus::CLOSED);
            break;
          default:
            // TODO: For now, we're just shipping the rest of these through
            // without acting on them.
            break;
        }
        return null;
    }

    return parent::applyCustomInternalTransaction($object, $xaction);
  }

  protected function expandTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    $results = parent::expandTransaction($object, $xaction);
    switch ($xaction->getTransactionType()) {
      case DifferentialTransaction::TYPE_ACTION:
        switch ($xaction->getNewValue()) {
          case DifferentialAction::ACTION_RESIGN:
            // If the user is resigning, add a separate reviewer edit
            // transaction which removes them as a reviewer.

            $actor_phid = $this->getActor()->getPHID();
            $type_edge = PhabricatorTransactions::TYPE_EDGE;
            $edge_reviewer = PhabricatorEdgeConfig::TYPE_DREV_HAS_REVIEWER;

            $results[] = id(new DifferentialTransaction())
              ->setTransactionType($type_edge)
              ->setMetadataValue('edge:type', $edge_reviewer)
              ->setIgnoreOnNoEffect(true)
              ->setNewValue(
                array(
                  '-' => array(
                    $actor_phid => $actor_phid,
                  ),
                ));

            break;
        }
      break;
    }

    return $results;
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorTransactions::TYPE_VIEW_POLICY:
      case PhabricatorTransactions::TYPE_EDIT_POLICY:
        return;
      case PhabricatorTransactions::TYPE_SUBSCRIBERS:
      case PhabricatorTransactions::TYPE_EDGE:
      case PhabricatorTransactions::TYPE_COMMENT:
      case DifferentialTransaction::TYPE_ACTION:
      case DifferentialTransaction::TYPE_INLINE:
        return;
    }

    return parent::applyCustomExternalTransaction($object, $xaction);
  }

  protected function validateTransaction(
    PhabricatorLiskDAO $object,
    $type,
    array $xactions) {

    $errors = parent::validateTransaction($object, $type, $xactions);

    foreach ($xactions as $xaction) {
      switch ($type) {
        case DifferentialTransaction::TYPE_ACTION:
          $error = $this->validateDifferentialAction(
            $object,
            $type,
            $xaction,
            $xaction->getNewValue());
          if ($error) {
            $errors[] = new PhabricatorApplicationTransactionValidationError(
              $type,
              pht('Invalid'),
              $error,
              $xaction);
          }
          break;
      }
    }

    return $errors;
  }

  private function validateDifferentialAction(
    DifferentialRevision $revision,
    $type,
    DifferentialTransaction $xaction,
    $action) {

    $author_phid = $revision->getAuthorPHID();
    $actor_phid = $this->getActor()->getPHID();
    $actor_is_author = ($author_phid == $actor_phid);

    $config_close_key = 'differential.always-allow-close';
    $always_allow_close = PhabricatorEnv::getEnvConfig($config_close_key);

    $config_reopen_key = 'differential.allow-reopen';
    $allow_reopen = PhabricatorEnv::getEnvConfig($config_reopen_key);

    $revision_status = $revision->getStatus();

    $status_accepted = ArcanistDifferentialRevisionStatus::ACCEPTED;
    $status_abandoned = ArcanistDifferentialRevisionStatus::ABANDONED;
    $status_closed = ArcanistDifferentialRevisionStatus::CLOSED;

    switch ($action) {
      case DifferentialAction::ACTION_RESIGN:
        // You can always resign from a revision if you're a reviewer. If you
        // aren't, this is a no-op rather than invalid.
        break;

      case DifferentialAction::ACTION_ABANDON:
        if (!$actor_is_author) {
          return pht(
            "You can not abandon this revision because you do not own it. ".
            "You can only abandon revisions you own.");
        }

        if ($revision_status == $status_closed) {
          return pht(
            "You can not abandon this revision because it has already been ".
            "closed.");
        }

        // NOTE: Abandons of already-abandoned revisions are treated as no-op
        // instead of invalid. Other abandons are OK.

        break;

      case DifferentialAction::ACTION_RECLAIM:
        if (!$actor_is_author) {
          return pht(
            "You can not reclaim this revision because you do not own ".
            "it. You can only reclaim revisions you own.");
        }

        if ($revision_status == $status_closed) {
          return pht(
            "You can not reclaim this revision because it has already been ".
            "closed.");
        }

        // NOTE: Reclaims of other non-abandoned revisions are treated as no-op
        // instead of invalid.

        break;

      case DifferentialAction::ACTION_REOPEN:
        if (!$allow_reopen) {
          return pht(
            'The reopen action is not enabled on this Phabricator install. '.
            'Adjust your configuration to enable it.');
        }

        // NOTE: If the revision is not closed, this is caught as a no-op
        // instead of an invalid transaction.

        break;

      case DifferentialAction::ACTION_RETHINK:
        if (!$actor_is_author) {
          return pht(
            "You can not plan changes to this revision because you do not ".
            "own it. To plan chagnes to a revision, you must be its owner.");
        }

        switch ($revision_status) {
          case ArcanistDifferentialRevisionStatus::ACCEPTED:
          case ArcanistDifferentialRevisionStatus::NEEDS_REVISION:
          case ArcanistDifferentialRevisionStatus::NEEDS_REVIEW:
            // These are OK.
            break;
          case ArcanistDifferentialRevisionStatus::ABANDONED:
            return pht(
              "You can not plan changes to this revision because it has ".
              "been abandoned.");
          case ArcanistDifferentialRevisionStatus::CLOSED:
            return pht(
              "You can not plan changes to this revision because it has ".
              "already been closed.");
          default:
            throw new Exception(
              pht(
                'Encountered unexpected revision status ("%s") when '.
                'validating "%s" action.',
                $revision_status,
                $action));
        }
        break;

      case DifferentialAction::ACTION_REQUEST:
        if (!$actor_is_author) {
          return pht(
            "You can not request review of this revision because you do ".
            "not own it. To request review of a revision, you must be its ".
            "owner.");
        }

        switch ($revision_status) {
          case ArcanistDifferentialRevisionStatus::ACCEPTED:
          case ArcanistDifferentialRevisionStatus::NEEDS_REVISION:
            // These are OK.
            break;
          case ArcanistDifferentialRevisionStatus::NEEDS_REVIEW:
            // This will be caught as "no effect" later on.
            break;
          case ArcanistDifferentialRevisionStatus::ABANDONED:
            return pht(
              "You can not request review of this revision because it has ".
              "been abandoned. Instead, reclaim it.");
          case ArcanistDifferentialRevisionStatus::CLOSED:
            return pht(
              "You can not request review of this revision because it has ".
              "already been closed.");
          default:
            throw new Exception(
              pht(
                'Encountered unexpected revision status ("%s") when '.
                'validating "%s" action.',
                $revision_status,
                $action));
        }
        break;

      case DifferentialAction::ACTION_CLOSE:

        // TODO: Permit the daemons to take this action in all cases.

        if (!$actor_is_author && !$always_allow_close) {
          return pht(
            "You can not close this revision because you do not own it. To ".
            "close a revision, you must be its owner.");
        }

        if ($revision_status != $status_accepted) {
          return pht(
            "You can not close this revision because it has not been ".
            "accepted. You can only close accepted revisions.");
        }
        break;
    }

    return null;
  }

  protected function sortTransactions(array $xactions) {
    $head = array();
    $tail = array();

    // Move bare comments to the end, so the actions precede them.
    foreach ($xactions as $xaction) {
      $type = $xaction->getTransactionType();
      if ($type == DifferentialTransaction::TYPE_INLINE) {
        $tail[] = $xaction;
      } else {
        $head[] = $xaction;
      }
    }

    return array_values(array_merge($head, $tail));
  }

  protected function requireCapabilities(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
    }

    return parent::requireCapabilities($object, $xaction);
  }

  protected function shouldSendMail(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return true;
  }

  protected function getMailTo(PhabricatorLiskDAO $object) {
    $phids = array();
    $phids[] = $object->getAuthorPHID();
    foreach ($object->getReviewerStatus() as $reviewer) {
      $phids[] = $reviewer->getReviewerPHID();
    }
    return $phids;
  }

  protected function getMailSubjectPrefix() {
    return PhabricatorEnv::getEnvConfig('metamta.differential.subject-prefix');
  }

  protected function buildReplyHandler(PhabricatorLiskDAO $object) {
    return id(new DifferentialReplyHandler())
      ->setMailReceiver($object);
  }

  protected function buildMailTemplate(PhabricatorLiskDAO $object) {
    $id = $object->getID();
    $title = $object->getTitle();

    $original_title = $object->getOriginalTitle();

    $subject = "D{$id}: {$title}";
    $thread_topic = "D{$id}: {$original_title}";

    return id(new PhabricatorMetaMTAMail())
      ->setSubject($subject)
      ->addHeader('Thread-Topic', $thread_topic);
  }

  protected function buildMailBody(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $body = parent::buildMailBody($object, $xactions);

    $body->addTextSection(
      pht('REVISION DETAIL'),
      PhabricatorEnv::getProductionURI('/D'.$object->getID()));

    return $body;
  }

  protected function supportsSearch() {
    return true;
  }

  protected function extractFilePHIDsFromCustomTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
    }

    return parent::extractFilePHIDsFromCustomTransaction($object, $xaction);
  }

}
