<?php

final class DifferentialTransactionEditor
  extends PhabricatorApplicationTransactionEditor {

  private $changedPriorToCommitURI;
  private $isCloseByCommit;
  private $repositoryPHIDOverride = false;
  private $didExpandInlineState = false;
  private $affectedPaths;

  public function getEditorApplicationClass() {
    return 'PhabricatorDifferentialApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Differential Revisions');
  }

  public function getDiffUpdateTransaction(array $xactions) {
    $type_update = DifferentialTransaction::TYPE_UPDATE;

    foreach ($xactions as $xaction) {
      if ($xaction->getTransactionType() == $type_update) {
        return $xaction;
      }
    }

    return null;
  }

  public function setIsCloseByCommit($is_close_by_commit) {
    $this->isCloseByCommit = $is_close_by_commit;
    return $this;
  }

  public function getIsCloseByCommit() {
    return $this->isCloseByCommit;
  }

  public function setChangedPriorToCommitURI($uri) {
    $this->changedPriorToCommitURI = $uri;
    return $this;
  }

  public function getChangedPriorToCommitURI() {
    return $this->changedPriorToCommitURI;
  }

  public function setRepositoryPHIDOverride($phid_or_null) {
    $this->repositoryPHIDOverride = $phid_or_null;
    return $this;
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhabricatorTransactions::TYPE_COMMENT;
    $types[] = PhabricatorTransactions::TYPE_VIEW_POLICY;
    $types[] = PhabricatorTransactions::TYPE_EDIT_POLICY;

    $types[] = DifferentialTransaction::TYPE_ACTION;
    $types[] = DifferentialTransaction::TYPE_INLINE;
    $types[] = DifferentialTransaction::TYPE_STATUS;
    $types[] = DifferentialTransaction::TYPE_UPDATE;

    return $types;
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case DifferentialTransaction::TYPE_ACTION:
        return null;
      case DifferentialTransaction::TYPE_INLINE:
        return null;
      case DifferentialTransaction::TYPE_UPDATE:
        if ($this->getIsNewObject()) {
          return null;
        } else {
          return $object->getActiveDiff()->getPHID();
        }
    }

    return parent::getCustomTransactionOldValue($object, $xaction);
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case DifferentialTransaction::TYPE_ACTION:
      case DifferentialTransaction::TYPE_UPDATE:
        return $xaction->getNewValue();
      case DifferentialTransaction::TYPE_INLINE:
        return null;
    }

    return parent::getCustomTransactionNewValue($object, $xaction);
  }

  protected function transactionHasEffect(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    $actor_phid = $this->getActingAsPHID();

    switch ($xaction->getTransactionType()) {
      case DifferentialTransaction::TYPE_INLINE:
        return $xaction->hasComment();
      case DifferentialTransaction::TYPE_ACTION:
        $status_closed = ArcanistDifferentialRevisionStatus::CLOSED;
        $status_abandoned = ArcanistDifferentialRevisionStatus::ABANDONED;
        $status_review = ArcanistDifferentialRevisionStatus::NEEDS_REVIEW;
        $status_revision = ArcanistDifferentialRevisionStatus::NEEDS_REVISION;
        $status_plan = ArcanistDifferentialRevisionStatus::CHANGES_PLANNED;

        $action_type = $xaction->getNewValue();
        switch ($action_type) {
          case DifferentialAction::ACTION_ACCEPT:
          case DifferentialAction::ACTION_REJECT:
            if ($action_type == DifferentialAction::ACTION_ACCEPT) {
              $new_status = DifferentialReviewerStatus::STATUS_ACCEPTED;
            } else {
              $new_status = DifferentialReviewerStatus::STATUS_REJECTED;
            }

            $actor = $this->getActor();

            // These transactions can cause effects in two ways: by altering the
            // status of an existing reviewer; or by adding the actor as a new
            // reviewer.

            $will_add_reviewer = true;
            foreach ($object->getReviewerStatus() as $reviewer) {
              if ($reviewer->hasAuthority($actor)) {
                if ($reviewer->getStatus() != $new_status) {
                  return true;
                }
              }
              if ($reviewer->getReviewerPHID() == $actor_phid) {
                $will_add_reviwer = false;
              }
            }

            return $will_add_reviewer;
          case DifferentialAction::ACTION_CLOSE:
            return ($object->getStatus() != $status_closed);
          case DifferentialAction::ACTION_ABANDON:
            return ($object->getStatus() != $status_abandoned);
          case DifferentialAction::ACTION_RECLAIM:
            return ($object->getStatus() == $status_abandoned);
          case DifferentialAction::ACTION_REOPEN:
            return ($object->getStatus() == $status_closed);
          case DifferentialAction::ACTION_RETHINK:
            return ($object->getStatus() != $status_plan);
          case DifferentialAction::ACTION_REQUEST:
            return ($object->getStatus() != $status_review);
          case DifferentialAction::ACTION_RESIGN:
            foreach ($object->getReviewerStatus() as $reviewer) {
              if ($reviewer->getReviewerPHID() == $actor_phid) {
                return true;
              }
            }
            return false;
          case DifferentialAction::ACTION_CLAIM:
            return ($actor_phid != $object->getAuthorPHID());
        }
    }

    return parent::transactionHasEffect($object, $xaction);
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    $status_review = ArcanistDifferentialRevisionStatus::NEEDS_REVIEW;
    $status_revision = ArcanistDifferentialRevisionStatus::NEEDS_REVISION;
    $status_plan = ArcanistDifferentialRevisionStatus::CHANGES_PLANNED;
    $status_abandoned = ArcanistDifferentialRevisionStatus::ABANDONED;
    $status_accepted = ArcanistDifferentialRevisionStatus::ACCEPTED;

    switch ($xaction->getTransactionType()) {
      case DifferentialTransaction::TYPE_INLINE:
        return;
      case DifferentialTransaction::TYPE_UPDATE:
        if (!$this->getIsCloseByCommit()) {
          switch ($object->getStatus()) {
            case $status_revision:
            case $status_plan:
            case $status_abandoned:
              $object->setStatus($status_review);
              break;
          }
        }

        $diff = $this->requireDiff($xaction->getNewValue());

        $object->setLineCount($diff->getLineCount());
        if ($this->repositoryPHIDOverride !== false) {
          $object->setRepositoryPHID($this->repositoryPHIDOverride);
        } else {
          $object->setRepositoryPHID($diff->getRepositoryPHID());
        }
        $object->attachActiveDiff($diff);

        // TODO: Update the `diffPHID` once we add that.
        return;
      case DifferentialTransaction::TYPE_ACTION:
        switch ($xaction->getNewValue()) {
          case DifferentialAction::ACTION_RESIGN:
          case DifferentialAction::ACTION_ACCEPT:
          case DifferentialAction::ACTION_REJECT:
            // These have no direct effects, and affect review status only
            // indirectly by altering reviewers with TYPE_EDGE transactions.
            return;
          case DifferentialAction::ACTION_ABANDON:
            $object->setStatus(ArcanistDifferentialRevisionStatus::ABANDONED);
            return;
          case DifferentialAction::ACTION_RETHINK:
            $object->setStatus($status_plan);
            return;
          case DifferentialAction::ACTION_RECLAIM:
            $object->setStatus($status_review);
            return;
          case DifferentialAction::ACTION_REOPEN:
            $object->setStatus($status_review);
            return;
          case DifferentialAction::ACTION_REQUEST:
            $object->setStatus($status_review);
            return;
          case DifferentialAction::ACTION_CLOSE:
            $old_status = $object->getStatus();
            $object->setStatus(ArcanistDifferentialRevisionStatus::CLOSED);
            $was_accepted = ($old_status == $status_accepted);
            $object->setProperty(
              DifferentialRevision::PROPERTY_CLOSED_FROM_ACCEPTED,
              $was_accepted);
            return;
          case DifferentialAction::ACTION_CLAIM:
            $object->setAuthorPHID($this->getActingAsPHID());
            return;
          default:
            throw new Exception(
              pht(
                'Differential action "%s" is not a valid action!',
                $xaction->getNewValue()));
        }
        break;
    }

    return parent::applyCustomInternalTransaction($object, $xaction);
  }

  protected function expandTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    $results = parent::expandTransaction($object, $xaction);

    $actor = $this->getActor();
    $actor_phid = $this->getActingAsPHID();
    $type_edge = PhabricatorTransactions::TYPE_EDGE;

    $status_plan = ArcanistDifferentialRevisionStatus::CHANGES_PLANNED;

    $edge_reviewer = DifferentialRevisionHasReviewerEdgeType::EDGECONST;
    $edge_ref_task = DifferentialRevisionHasTaskEdgeType::EDGECONST;

    $is_sticky_accept = PhabricatorEnv::getEnvConfig(
      'differential.sticky-accept');

    $downgrade_rejects = false;
    $downgrade_accepts = false;
    if ($this->getIsCloseByCommit()) {
      // Never downgrade reviewers when we're closing a revision after a
      // commit.
    } else {
      switch ($xaction->getTransactionType()) {
        case DifferentialTransaction::TYPE_UPDATE:
          $downgrade_rejects = true;
          if (!$is_sticky_accept) {
            // If "sticky accept" is disabled, also downgrade the accepts.
            $downgrade_accepts = true;
          }
          break;
        case DifferentialTransaction::TYPE_ACTION:
          switch ($xaction->getNewValue()) {
            case DifferentialAction::ACTION_REQUEST:
              $downgrade_rejects = true;
              if ((!$is_sticky_accept) ||
                  ($object->getStatus() != $status_plan)) {
                // If the old state isn't "changes planned", downgrade the
                // accepts. This exception allows an accepted revision to
                // go through Plan Changes -> Request Review to return to
                // "accepted" if the author didn't update the revision.
                $downgrade_accepts = true;
              }
              break;
          }
          break;
      }
    }

    $new_accept = DifferentialReviewerStatus::STATUS_ACCEPTED;
    $new_reject = DifferentialReviewerStatus::STATUS_REJECTED;
    $old_accept = DifferentialReviewerStatus::STATUS_ACCEPTED_OLDER;
    $old_reject = DifferentialReviewerStatus::STATUS_REJECTED_OLDER;

    if ($downgrade_rejects || $downgrade_accepts) {
      // When a revision is updated, change all "reject" to "rejected older
      // revision". This means we won't immediately push the update back into
      // "needs review", but outstanding rejects will still block it from
      // moving to "accepted".

      // We also do this for "Request Review", even though the diff is not
      // updated directly. Essentially, this acts like an update which doesn't
      // actually change the diff text.

      $edits = array();
      foreach ($object->getReviewerStatus() as $reviewer) {
        if ($downgrade_rejects) {
          if ($reviewer->getStatus() == $new_reject) {
            $edits[$reviewer->getReviewerPHID()] = array(
              'data' => array(
                'status' => $old_reject,
              ),
            );
          }
        }

        if ($downgrade_accepts) {
          if ($reviewer->getStatus() == $new_accept) {
            $edits[$reviewer->getReviewerPHID()] = array(
              'data' => array(
                'status' => $old_accept,
              ),
            );
          }
        }
      }

      if ($edits) {
        $results[] = id(new DifferentialTransaction())
          ->setTransactionType($type_edge)
          ->setMetadataValue('edge:type', $edge_reviewer)
          ->setIgnoreOnNoEffect(true)
          ->setNewValue(array('+' => $edits));
      }
    }

    switch ($xaction->getTransactionType()) {
      case DifferentialTransaction::TYPE_UPDATE:
        if ($this->getIsCloseByCommit()) {
          // Don't bother with any of this if this update is a side effect of
          // commit detection.
          break;
        }

        // When a revision is updated and the diff comes from a branch named
        // "T123" or similar, automatically associate the commit with the
        // task that the branch names.

        $maniphest = 'PhabricatorManiphestApplication';
        if (PhabricatorApplication::isClassInstalled($maniphest)) {
          $diff = $this->requireDiff($xaction->getNewValue());
          $branch = $diff->getBranch();

          // No "$", to allow for branches like T123_demo.
          $match = null;
          if (preg_match('/^T(\d+)/i', $branch, $match)) {
            $task_id = $match[1];
            $tasks = id(new ManiphestTaskQuery())
              ->setViewer($this->getActor())
              ->withIDs(array($task_id))
              ->execute();
            if ($tasks) {
              $task = head($tasks);
              $task_phid = $task->getPHID();

              $results[] = id(new DifferentialTransaction())
                ->setTransactionType($type_edge)
                ->setMetadataValue('edge:type', $edge_ref_task)
                ->setIgnoreOnNoEffect(true)
                ->setNewValue(array('+' => array($task_phid => $task_phid)));
            }
          }
        }
        break;

      case PhabricatorTransactions::TYPE_COMMENT:
        // When a user leaves a comment, upgrade their reviewer status from
        // "added" to "commented" if they're also a reviewer. We may further
        // upgrade this based on other actions in the transaction group.

        $status_added = DifferentialReviewerStatus::STATUS_ADDED;
        $status_commented = DifferentialReviewerStatus::STATUS_COMMENTED;

        $data = array(
          'status' => $status_commented,
        );

        $edits = array();
        foreach ($object->getReviewerStatus() as $reviewer) {
          if ($reviewer->getReviewerPHID() == $actor_phid) {
            if ($reviewer->getStatus() == $status_added) {
              $edits[$actor_phid] = array(
                'data' => $data,
              );
            }
          }
        }

        if ($edits) {
          $results[] = id(new DifferentialTransaction())
            ->setTransactionType($type_edge)
            ->setMetadataValue('edge:type', $edge_reviewer)
            ->setIgnoreOnNoEffect(true)
            ->setNewValue(array('+' => $edits));
        }
        break;

      case DifferentialTransaction::TYPE_ACTION:
        $action_type = $xaction->getNewValue();

        switch ($action_type) {
          case DifferentialAction::ACTION_ACCEPT:
          case DifferentialAction::ACTION_REJECT:
            if ($action_type == DifferentialAction::ACTION_ACCEPT) {
              $data = array(
                'status' => DifferentialReviewerStatus::STATUS_ACCEPTED,
              );
            } else {
              $data = array(
                'status' => DifferentialReviewerStatus::STATUS_REJECTED,
              );
            }

            $edits = array();

            foreach ($object->getReviewerStatus() as $reviewer) {
              if ($reviewer->hasAuthority($actor)) {
                $edits[$reviewer->getReviewerPHID()] = array(
                  'data' => $data,
                );
              }
            }

            // Also either update or add the actor themselves as a reviewer.
            $edits[$actor_phid] = array(
              'data' => $data,
            );

            $results[] = id(new DifferentialTransaction())
              ->setTransactionType($type_edge)
              ->setMetadataValue('edge:type', $edge_reviewer)
              ->setIgnoreOnNoEffect(true)
              ->setNewValue(array('+' => $edits));
            break;

          case DifferentialAction::ACTION_CLAIM:
            // If the user is commandeering, add the previous owner as a
            // reviewer and remove the actor.

            $edits = array(
              '-' => array(
                $actor_phid => $actor_phid,
              ),
            );

            $owner_phid = $object->getAuthorPHID();
            if ($owner_phid) {
              $reviewer = new DifferentialReviewer(
                $owner_phid,
                array(
                  'status' => DifferentialReviewerStatus::STATUS_ADDED,
                ));

              $edits['+'] = array(
                $owner_phid => array(
                  'data' => $reviewer->getEdgeData(),
                ),
              );
            }

            // NOTE: We're setting setIsCommandeerSideEffect() on this because
            // normally you can't add a revision's author as a reviewer, but
            // this action swaps them after validation executes.

            $results[] = id(new DifferentialTransaction())
              ->setTransactionType($type_edge)
              ->setMetadataValue('edge:type', $edge_reviewer)
              ->setIgnoreOnNoEffect(true)
              ->setIsCommandeerSideEffect(true)
              ->setNewValue($edits);

            break;
          case DifferentialAction::ACTION_RESIGN:
            // If the user is resigning, add a separate reviewer edit
            // transaction which removes them as a reviewer.

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

    if (!$this->didExpandInlineState) {
      switch ($xaction->getTransactionType()) {
        case PhabricatorTransactions::TYPE_COMMENT:
        case DifferentialTransaction::TYPE_ACTION:
        case DifferentialTransaction::TYPE_UPDATE:
        case DifferentialTransaction::TYPE_INLINE:
          $this->didExpandInlineState = true;

          $actor_phid = $this->getActingAsPHID();
          $actor_is_author = ($object->getAuthorPHID() == $actor_phid);
          if (!$actor_is_author) {
            break;
          }

          $state_map = PhabricatorTransactions::getInlineStateMap();

          $inlines = id(new DifferentialDiffInlineCommentQuery())
            ->setViewer($this->getActor())
            ->withRevisionPHIDs(array($object->getPHID()))
            ->withFixedStates(array_keys($state_map))
            ->execute();

          if (!$inlines) {
            break;
          }

          $old_value = mpull($inlines, 'getFixedState', 'getPHID');
          $new_value = array();
          foreach ($old_value as $key => $state) {
            $new_value[$key] = $state_map[$state];
          }

          $results[] = id(new DifferentialTransaction())
            ->setTransactionType(PhabricatorTransactions::TYPE_INLINESTATE)
            ->setIgnoreOnNoEffect(true)
            ->setOldValue($old_value)
            ->setNewValue($new_value);
          break;
      }
    }

    return $results;
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case DifferentialTransaction::TYPE_ACTION:
        return;
      case DifferentialTransaction::TYPE_INLINE:
        $reply = $xaction->getComment()->getReplyToComment();
        if ($reply && !$reply->getHasReplies()) {
          $reply->setHasReplies(1)->save();
        }
        return;
      case DifferentialTransaction::TYPE_UPDATE:
        // Now that we're inside the transaction, do a final check.
        $diff = $this->requireDiff($xaction->getNewValue());

        // TODO: It would be slightly cleaner to just revalidate this
        // transaction somehow using the same validation code, but that's
        // not easy to do at the moment.

        $revision_id = $diff->getRevisionID();
        if ($revision_id && ($revision_id != $object->getID())) {
          throw new Exception(
            pht(
              'Diff is already attached to another revision. You lost '.
              'a race?'));
        }

        // TODO: This can race with diff updates, particularly those from
        // Harbormaster. See discussion in T8650.
        $diff->setRevisionID($object->getID());
        $diff->save();

        // Update Harbormaster to set the containerPHID correctly for any
        // existing buildables. We may otherwise have buildables stuck with
        // the old (`null`) container.

        // TODO: This is a bit iffy, maybe we can find a cleaner approach?
        // In particular, this could (rarely) be overwritten by Harbormaster
        // workers.
        $table = new HarbormasterBuildable();
        $conn_w = $table->establishConnection('w');
        queryfx(
          $conn_w,
          'UPDATE %T SET containerPHID = %s WHERE buildablePHID = %s',
          $table->getTableName(),
          $object->getPHID(),
          $diff->getPHID());

        return;
    }

    return parent::applyCustomExternalTransaction($object, $xaction);
  }

  protected function applyBuiltinExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorTransactions::TYPE_INLINESTATE:
        $table = new DifferentialTransactionComment();
        $conn_w = $table->establishConnection('w');
        foreach ($xaction->getNewValue() as $phid => $state) {
          queryfx(
            $conn_w,
            'UPDATE %T SET fixedState = %s WHERE phid = %s',
            $table->getTableName(),
            $state,
            $phid);
        }
        break;
    }

    return parent::applyBuiltinExternalTransaction($object, $xaction);
  }

  protected function mergeEdgeData($type, array $u, array $v) {
    $result = parent::mergeEdgeData($type, $u, $v);

    switch ($type) {
      case DifferentialRevisionHasReviewerEdgeType::EDGECONST:
        // When the same reviewer has their status updated by multiple
        // transactions, we want the strongest status to win. An example of
        // this is when a user adds a comment and also accepts a revision which
        // they are a reviewer on. The comment creates a "commented" status,
        // while the accept creates an "accepted" status. Since accept is
        // stronger, it should win and persist.

        $u_status = idx($u, 'status');
        $v_status = idx($v, 'status');
        $u_str = DifferentialReviewerStatus::getStatusStrength($u_status);
        $v_str = DifferentialReviewerStatus::getStatusStrength($v_status);
        if ($u_str > $v_str) {
          $result['status'] = $u_status;
        } else {
          $result['status'] = $v_status;
        }
        break;
    }

    return $result;
  }

  protected function applyFinalEffects(
    PhabricatorLiskDAO $object,
    array $xactions) {

    // Load the most up-to-date version of the revision and its reviewers,
    // so we don't need to try to deduce the state of reviewers by examining
    // all the changes made by the transactions. Then, update the reviewers
    // on the object to make sure we're acting on the current reviewer set
    // (and, for example, sending mail to the right people).

    $new_revision = id(new DifferentialRevisionQuery())
      ->setViewer($this->getActor())
      ->needReviewerStatus(true)
      ->needActiveDiffs(true)
      ->withIDs(array($object->getID()))
      ->executeOne();
    if (!$new_revision) {
      throw new Exception(
        pht('Failed to load revision from transaction finalization.'));
    }

    $object->attachReviewerStatus($new_revision->getReviewerStatus());
    $object->attachActiveDiff($new_revision->getActiveDiff());
    $object->attachRepository($new_revision->getRepository());

    foreach ($xactions as $xaction) {
      switch ($xaction->getTransactionType()) {
        case DifferentialTransaction::TYPE_UPDATE:
          $diff = $this->requireDiff($xaction->getNewValue(), true);

          // Update these denormalized index tables when we attach a new
          // diff to a revision.

          $this->updateRevisionHashTable($object, $diff);
          $this->updateAffectedPathTable($object, $diff);
          break;
      }
    }

    $status_accepted = ArcanistDifferentialRevisionStatus::ACCEPTED;
    $status_revision = ArcanistDifferentialRevisionStatus::NEEDS_REVISION;
    $status_review = ArcanistDifferentialRevisionStatus::NEEDS_REVIEW;

    $old_status = $object->getStatus();
    switch ($old_status) {
      case $status_accepted:
      case $status_revision:
      case $status_review:
        // Try to move a revision to "accepted". We look for:
        //
        //   - at least one accepting reviewer who is a user; and
        //   - no rejects; and
        //   - no rejects of older diffs; and
        //   - no blocking reviewers.

        $has_accepting_user = false;
        $has_rejecting_reviewer = false;
        $has_rejecting_older_reviewer = false;
        $has_blocking_reviewer = false;
        foreach ($object->getReviewerStatus() as $reviewer) {
          $reviewer_status = $reviewer->getStatus();
          switch ($reviewer_status) {
            case DifferentialReviewerStatus::STATUS_REJECTED:
              $has_rejecting_reviewer = true;
              break;
            case DifferentialReviewerStatus::STATUS_REJECTED_OLDER:
              $has_rejecting_older_reviewer = true;
              break;
            case DifferentialReviewerStatus::STATUS_BLOCKING:
              $has_blocking_reviewer = true;
              break;
            case DifferentialReviewerStatus::STATUS_ACCEPTED:
              if ($reviewer->isUser()) {
                $has_accepting_user = true;
              }
              break;
          }
        }

        $new_status = null;
        if ($has_accepting_user &&
            !$has_rejecting_reviewer &&
            !$has_rejecting_older_reviewer &&
            !$has_blocking_reviewer) {
          $new_status = $status_accepted;
        } else if ($has_rejecting_reviewer) {
          // This isn't accepted, and there's at least one rejecting reviewer,
          // so the revision needs changes. This usually happens after a
          // "reject".
          $new_status = $status_revision;
        } else if ($old_status == $status_accepted) {
          // This revision was accepted, but it no longer satisfies the
          // conditions for acceptance. This usually happens after an accepting
          // reviewer resigns or is removed.
          $new_status = $status_review;
        }

        if ($new_status !== null && ($new_status != $old_status)) {
          $xaction = id(new DifferentialTransaction())
            ->setTransactionType(DifferentialTransaction::TYPE_STATUS)
            ->setOldValue($old_status)
            ->setNewValue($new_status);

          $xaction = $this->populateTransaction($object, $xaction)->save();

          $xactions[] = $xaction;

          $object->setStatus($new_status)->save();
        }
        break;
      default:
        // Revisions can't transition out of other statuses (like closed or
        // abandoned) as a side effect of reviewer status changes.
        break;
    }

    return $xactions;
  }

  protected function validateTransaction(
    PhabricatorLiskDAO $object,
    $type,
    array $xactions) {

    $errors = parent::validateTransaction($object, $type, $xactions);

    $config_self_accept_key = 'differential.allow-self-accept';
    $allow_self_accept = PhabricatorEnv::getEnvConfig($config_self_accept_key);

    foreach ($xactions as $xaction) {
      switch ($type) {
        case PhabricatorTransactions::TYPE_EDGE:
          switch ($xaction->getMetadataValue('edge:type')) {
            case DifferentialRevisionHasReviewerEdgeType::EDGECONST:

              // Prevent the author from becoming a reviewer.

              // NOTE: This is pretty gross, but this restriction is unusual.
              // If we end up with too much more of this, we should try to clean
              // this up -- maybe by moving validation to after transactions
              // are adjusted (so we can just examine the final value) or adding
              // a second phase there?

              $author_phid = $object->getAuthorPHID();
              $new = $xaction->getNewValue();

              $add = idx($new, '+', array());
              $eq = idx($new, '=', array());
              $phids = array_keys($add + $eq);

              foreach ($phids as $phid) {
                if (($phid == $author_phid) &&
                    !$allow_self_accept &&
                    !$xaction->getIsCommandeerSideEffect()) {
                  $errors[] =
                    new PhabricatorApplicationTransactionValidationError(
                      $type,
                      pht('Invalid'),
                      pht('The author of a revision can not be a reviewer.'),
                      $xaction);
                }
              }
              break;
          }
          break;
        case DifferentialTransaction::TYPE_UPDATE:
          $diff = $this->loadDiff($xaction->getNewValue());
          if (!$diff) {
            $errors[] = new PhabricatorApplicationTransactionValidationError(
              $type,
              pht('Invalid'),
              pht('The specified diff does not exist.'),
              $xaction);
          } else if (($diff->getRevisionID()) &&
            ($diff->getRevisionID() != $object->getID())) {
            $errors[] = new PhabricatorApplicationTransactionValidationError(
              $type,
              pht('Invalid'),
              pht(
                'You can not update this revision to the specified diff, '.
                'because the diff is already attached to another revision.'),
              $xaction);
          }
          break;
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
    $actor_phid = $this->getActingAsPHID();
    $actor_is_author = ($author_phid == $actor_phid);

    $config_abandon_key = 'differential.always-allow-abandon';
    $always_allow_abandon = PhabricatorEnv::getEnvConfig($config_abandon_key);

    $config_close_key = 'differential.always-allow-close';
    $always_allow_close = PhabricatorEnv::getEnvConfig($config_close_key);

    $config_reopen_key = 'differential.allow-reopen';
    $allow_reopen = PhabricatorEnv::getEnvConfig($config_reopen_key);

    $config_self_accept_key = 'differential.allow-self-accept';
    $allow_self_accept = PhabricatorEnv::getEnvConfig($config_self_accept_key);

    $revision_status = $revision->getStatus();

    $status_accepted = ArcanistDifferentialRevisionStatus::ACCEPTED;
    $status_abandoned = ArcanistDifferentialRevisionStatus::ABANDONED;
    $status_closed = ArcanistDifferentialRevisionStatus::CLOSED;

    switch ($action) {
      case DifferentialAction::ACTION_ACCEPT:
        if ($actor_is_author && !$allow_self_accept) {
          return pht(
            'You can not accept this revision because you are the owner.');
        }

        if ($revision_status == $status_abandoned) {
          return pht(
            'You can not accept this revision because it has been '.
            'abandoned.');
        }

        if ($revision_status == $status_closed) {
          return pht(
            'You can not accept this revision because it has already been '.
            'closed.');
        }

        // TODO: It would be nice to make this generic at some point.
        $signatures = DifferentialRequiredSignaturesField::loadForRevision(
          $revision);
        foreach ($signatures as $phid => $signed) {
          if (!$signed) {
            return pht(
              'You can not accept this revision because the author has '.
              'not signed all of the required legal documents.');
          }
        }

        break;

      case DifferentialAction::ACTION_REJECT:
        if ($actor_is_author) {
          return pht('You can not request changes to your own revision.');
        }

        if ($revision_status == $status_abandoned) {
          return pht(
            'You can not request changes to this revision because it has been '.
            'abandoned.');
        }

        if ($revision_status == $status_closed) {
          return pht(
            'You can not request changes to this revision because it has '.
            'already been closed.');
        }
        break;

      case DifferentialAction::ACTION_RESIGN:
        // You can always resign from a revision if you're a reviewer. If you
        // aren't, this is a no-op rather than invalid.
        break;

      case DifferentialAction::ACTION_CLAIM:
        // You can claim a revision if you're not the owner. If you are, this
        // is a no-op rather than invalid.

        if ($revision_status == $status_closed) {
          return pht(
            'You can not commandeer this revision because it has already been '.
            'closed.');
        }
        break;

      case DifferentialAction::ACTION_ABANDON:
        if (!$actor_is_author && !$always_allow_abandon) {
          return pht(
            'You can not abandon this revision because you do not own it. '.
            'You can only abandon revisions you own.');
        }

        if ($revision_status == $status_closed) {
          return pht(
            'You can not abandon this revision because it has already been '.
            'closed.');
        }

        // NOTE: Abandons of already-abandoned revisions are treated as no-op
        // instead of invalid. Other abandons are OK.

        break;

      case DifferentialAction::ACTION_RECLAIM:
        if (!$actor_is_author) {
          return pht(
            'You can not reclaim this revision because you do not own '.
            'it. You can only reclaim revisions you own.');
        }

        if ($revision_status == $status_closed) {
          return pht(
            'You can not reclaim this revision because it has already been '.
            'closed.');
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
            'You can not plan changes to this revision because you do not '.
            'own it. To plan changes to a revision, you must be its owner.');
        }

        switch ($revision_status) {
          case ArcanistDifferentialRevisionStatus::ACCEPTED:
          case ArcanistDifferentialRevisionStatus::NEEDS_REVISION:
          case ArcanistDifferentialRevisionStatus::NEEDS_REVIEW:
            // These are OK.
            break;
          case ArcanistDifferentialRevisionStatus::CHANGES_PLANNED:
            // Let this through, it's a no-op.
            break;
          case ArcanistDifferentialRevisionStatus::ABANDONED:
            return pht(
              'You can not plan changes to this revision because it has '.
              'been abandoned.');
          case ArcanistDifferentialRevisionStatus::CLOSED:
            return pht(
              'You can not plan changes to this revision because it has '.
              'already been closed.');
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
            'You can not request review of this revision because you do '.
            'not own it. To request review of a revision, you must be its '.
            'owner.');
        }

        switch ($revision_status) {
          case ArcanistDifferentialRevisionStatus::ACCEPTED:
          case ArcanistDifferentialRevisionStatus::NEEDS_REVISION:
          case ArcanistDifferentialRevisionStatus::CHANGES_PLANNED:
            // These are OK.
            break;
          case ArcanistDifferentialRevisionStatus::NEEDS_REVIEW:
            // This will be caught as "no effect" later on.
            break;
          case ArcanistDifferentialRevisionStatus::ABANDONED:
            return pht(
              'You can not request review of this revision because it has '.
              'been abandoned. Instead, reclaim it.');
          case ArcanistDifferentialRevisionStatus::CLOSED:
            return pht(
              'You can not request review of this revision because it has '.
              'already been closed.');
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
        // We force revisions closed when we discover a corresponding commit.
        // In this case, revisions are allowed to transition to closed from
        // any state. This is an automated action taken by the daemons.

        if (!$this->getIsCloseByCommit()) {
          if (!$actor_is_author && !$always_allow_close) {
            return pht(
              'You can not close this revision because you do not own it. To '.
              'close a revision, you must be its owner.');
          }

          if ($revision_status != $status_accepted) {
            return pht(
              'You can not close this revision because it has not been '.
              'accepted. You can only close accepted revisions.');
          }
        }
        break;
    }

    return null;
  }

  protected function sortTransactions(array $xactions) {
    $xactions = parent::sortTransactions($xactions);

    $head = array();
    $tail = array();

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

    switch ($xaction->getTransactionType()) {}

    return parent::requireCapabilities($object, $xaction);
  }

  protected function shouldPublishFeedStory(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return true;
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

  protected function getMailAction(
    PhabricatorLiskDAO $object,
    array $xactions) {
    $action = parent::getMailAction($object, $xactions);

    $strongest = $this->getStrongestAction($object, $xactions);
    switch ($strongest->getTransactionType()) {
      case DifferentialTransaction::TYPE_UPDATE:
        $count = new PhutilNumber($object->getLineCount());
        $action = pht('%s, %s line(s)', $action, $count);
        break;
    }

    return $action;
  }

  protected function getMailSubjectPrefix() {
    return PhabricatorEnv::getEnvConfig('metamta.differential.subject-prefix');
  }

  protected function getMailThreadID(PhabricatorLiskDAO $object) {
    // This is nonstandard, but retains threading with older messages.
    $phid = $object->getPHID();
    return "differential-rev-{$phid}-req";
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

    $body = new PhabricatorMetaMTAMailBody();
    $body->setViewer($this->requireActor());

    $revision_uri = PhabricatorEnv::getProductionURI('/D'.$object->getID());

    $this->addHeadersAndCommentsToMailBody(
      $body,
      $xactions,
      pht('View Revision'),
      $revision_uri);

    $type_inline = DifferentialTransaction::TYPE_INLINE;

    $inlines = array();
    foreach ($xactions as $xaction) {
      if ($xaction->getTransactionType() == $type_inline) {
        $inlines[] = $xaction;
      }
    }

    if ($inlines) {
      $this->appendInlineCommentsForMail($object, $inlines, $body);
    }

    $changed_uri = $this->getChangedPriorToCommitURI();
    if ($changed_uri) {
      $body->addLinkSection(
        pht('CHANGED PRIOR TO COMMIT'),
        $changed_uri);
    }

    $this->addCustomFieldsToMailBody($body, $object, $xactions);

    $body->addLinkSection(
      pht('REVISION DETAIL'),
      $revision_uri);

    $update_xaction = null;
    foreach ($xactions as $xaction) {
      switch ($xaction->getTransactionType()) {
        case DifferentialTransaction::TYPE_UPDATE:
          $update_xaction = $xaction;
          break;
      }
    }

    if ($update_xaction) {
      $diff = $this->requireDiff($update_xaction->getNewValue(), true);

      $body->addTextSection(
        pht('AFFECTED FILES'),
        $this->renderAffectedFilesForMail($diff));

      $config_key_inline = 'metamta.differential.inline-patches';
      $config_inline = PhabricatorEnv::getEnvConfig($config_key_inline);

      $config_key_attach = 'metamta.differential.attach-patches';
      $config_attach = PhabricatorEnv::getEnvConfig($config_key_attach);

      if ($config_inline || $config_attach) {
        $body_limit = PhabricatorEnv::getEnvConfig('metamta.email-body-limit');

        $patch = $this->buildPatchForMail($diff);
        if ($config_inline) {
          $lines = substr_count($patch, "\n");
          $bytes = strlen($patch);

          // Limit the patch size to the smaller of 256 bytes per line or
          // the mail body limit. This prevents degenerate behavior for patches
          // with one line that is 10MB long. See T11748.
          $byte_limits = array();
          $byte_limits[] = (256 * $config_inline);
          $byte_limits[] = $body_limit;
          $byte_limit = min($byte_limits);

          $lines_ok = ($lines <= $config_inline);
          $bytes_ok = ($bytes <= $byte_limit);

          if ($lines_ok && $bytes_ok) {
            $this->appendChangeDetailsForMail($object, $diff, $patch, $body);
          } else {
            // TODO: Provide a helpful message about the patch being too
            // large or lengthy here.
          }
        }

        if ($config_attach) {
          $name = pht('D%s.%s.patch', $object->getID(), $diff->getID());
          $mime_type = 'text/x-patch; charset=utf-8';
          $body->addAttachment(
            new PhabricatorMetaMTAAttachment($patch, $name, $mime_type));
        }
      }
    }

    return $body;
  }

  public function getMailTagsMap() {
    return array(
      DifferentialTransaction::MAILTAG_REVIEW_REQUEST =>
        pht('A revision is created.'),
      DifferentialTransaction::MAILTAG_UPDATED =>
        pht('A revision is updated.'),
      DifferentialTransaction::MAILTAG_COMMENT =>
        pht('Someone comments on a revision.'),
      DifferentialTransaction::MAILTAG_CLOSED =>
        pht('A revision is closed.'),
      DifferentialTransaction::MAILTAG_REVIEWERS =>
        pht("A revision's reviewers change."),
      DifferentialTransaction::MAILTAG_CC =>
        pht("A revision's CCs change."),
      DifferentialTransaction::MAILTAG_OTHER =>
        pht('Other revision activity not listed above occurs.'),
    );
  }

  protected function supportsSearch() {
    return true;
  }

  protected function expandCustomRemarkupBlockTransactions(
    PhabricatorLiskDAO $object,
    array $xactions,
    array $changes,
    PhutilMarkupEngine $engine) {

    $flat_blocks = mpull($changes, 'getNewValue');
    $huge_block = implode("\n\n", $flat_blocks);

    $task_map = array();
    $task_refs = id(new ManiphestCustomFieldStatusParser())
      ->parseCorpus($huge_block);
    foreach ($task_refs as $match) {
      foreach ($match['monograms'] as $monogram) {
        $task_id = (int)trim($monogram, 'tT');
        $task_map[$task_id] = true;
      }
    }

    $rev_map = array();
    $rev_refs = id(new DifferentialCustomFieldDependsOnParser())
      ->parseCorpus($huge_block);
    foreach ($rev_refs as $match) {
      foreach ($match['monograms'] as $monogram) {
        $rev_id = (int)trim($monogram, 'dD');
        $rev_map[$rev_id] = true;
      }
    }

    $edges = array();
    $task_phids = array();
    $rev_phids = array();

    if ($task_map) {
      $tasks = id(new ManiphestTaskQuery())
        ->setViewer($this->getActor())
        ->withIDs(array_keys($task_map))
        ->execute();

      if ($tasks) {
        $task_phids = mpull($tasks, 'getPHID', 'getPHID');
        $edge_related = DifferentialRevisionHasTaskEdgeType::EDGECONST;
        $edges[$edge_related] = $task_phids;
      }
    }

    if ($rev_map) {
      $revs = id(new DifferentialRevisionQuery())
        ->setViewer($this->getActor())
        ->withIDs(array_keys($rev_map))
        ->execute();
      $rev_phids = mpull($revs, 'getPHID', 'getPHID');

      // NOTE: Skip any write attempts if a user cleverly implies a revision
      // depends upon itself.
      unset($rev_phids[$object->getPHID()]);

      if ($revs) {
        $depends = DifferentialRevisionDependsOnRevisionEdgeType::EDGECONST;
        $edges[$depends] = $rev_phids;
      }
    }

    $this->setUnmentionablePHIDMap(array_merge($task_phids, $rev_phids));

    $result = array();
    foreach ($edges as $type => $specs) {
      $result[] = id(new DifferentialTransaction())
        ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
        ->setMetadataValue('edge:type', $type)
        ->setNewValue(array('+' => $specs));
    }

    return $result;
  }

  private function appendInlineCommentsForMail(
    PhabricatorLiskDAO $object,
    array $inlines,
    PhabricatorMetaMTAMailBody $body) {

    $section = id(new DifferentialInlineCommentMailView())
      ->setViewer($this->getActor())
      ->setInlines($inlines)
      ->buildMailSection();

    $header = pht('INLINE COMMENTS');

    $section_text = "\n".$section->getPlaintext();

    $style = array(
      'margin: 6px 0 12px 0;',
    );

    $section_html = phutil_tag(
      'div',
      array(
        'style' => implode(' ', $style),
      ),
      $section->getHTML());

    $body->addPlaintextSection($header, $section_text, false);
    $body->addHTMLSection($header, $section_html);
  }

  private function appendChangeDetailsForMail(
    PhabricatorLiskDAO $object,
    DifferentialDiff $diff,
    $patch,
    PhabricatorMetaMTAMailBody $body) {

    $section = id(new DifferentialChangeDetailMailView())
      ->setViewer($this->getActor())
      ->setDiff($diff)
      ->setPatch($patch)
      ->buildMailSection();

    $header = pht('CHANGE DETAILS');

    $section_text = "\n".$section->getPlaintext();

    $style = array(
      'margin: 6px 0 12px 0;',
    );

    $section_html = phutil_tag(
      'div',
      array(
        'style' => implode(' ', $style),
      ),
      $section->getHTML());

    $body->addPlaintextSection($header, $section_text, false);
    $body->addHTMLSection($header, $section_html);
  }

  private function loadDiff($phid, $need_changesets = false) {
    $query = id(new DifferentialDiffQuery())
      ->withPHIDs(array($phid))
      ->setViewer($this->getActor());

    if ($need_changesets) {
      $query->needChangesets(true);
    }

    return $query->executeOne();
  }

  private function requireDiff($phid, $need_changesets = false) {
    $diff = $this->loadDiff($phid, $need_changesets);
    if (!$diff) {
      throw new Exception(pht('Diff "%s" does not exist!', $phid));
    }

    return $diff;
  }

/* -(  Herald Integration  )------------------------------------------------- */

  protected function shouldApplyHeraldRules(
    PhabricatorLiskDAO $object,
    array $xactions) {

    if ($this->getIsNewObject()) {
      return true;
    }

    foreach ($xactions as $xaction) {
      switch ($xaction->getTransactionType()) {
        case DifferentialTransaction::TYPE_UPDATE:
          if (!$this->getIsCloseByCommit()) {
            return true;
          }
          break;
        case DifferentialTransaction::TYPE_ACTION:
          switch ($xaction->getNewValue()) {
            case DifferentialAction::ACTION_CLAIM:
              // When users commandeer revisions, we may need to trigger
              // signatures or author-based rules.
              return true;
          }
          break;
      }
    }

    return parent::shouldApplyHeraldRules($object, $xactions);
  }

  protected function didApplyHeraldRules(
    PhabricatorLiskDAO $object,
    HeraldAdapter $adapter,
    HeraldTranscript $transcript) {

    $repository = $object->getRepository();
    if (!$repository) {
      return array();
    }

    if (!$this->affectedPaths) {
      return array();
    }

    $packages = PhabricatorOwnersPackage::loadAffectedPackages(
      $repository,
      $this->affectedPaths);
    if (!$packages) {
      return array();
    }

    // Remove packages that the revision author is an owner of. If you own
    // code, you don't need another owner to review it.
    $authority = id(new PhabricatorOwnersPackageQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withPHIDs(mpull($packages, 'getPHID'))
      ->withAuthorityPHIDs(array($object->getAuthorPHID()))
      ->execute();
    $authority = mpull($authority, null, 'getPHID');

    foreach ($packages as $key => $package) {
      $package_phid = $package->getPHID();
      if (isset($authority[$package_phid])) {
        unset($packages[$key]);
        continue;
      }
    }

    if (!$packages) {
      return array();
    }

    $auto_subscribe = array();
    $auto_review = array();
    $auto_block = array();

    foreach ($packages as $package) {
      switch ($package->getAutoReview()) {
        case PhabricatorOwnersPackage::AUTOREVIEW_SUBSCRIBE:
          $auto_subscribe[] = $package;
          break;
        case PhabricatorOwnersPackage::AUTOREVIEW_REVIEW:
          $auto_review[] = $package;
          break;
        case PhabricatorOwnersPackage::AUTOREVIEW_BLOCK:
          $auto_block[] = $package;
          break;
        case PhabricatorOwnersPackage::AUTOREVIEW_NONE:
        default:
          break;
      }
    }

    $owners_phid = id(new PhabricatorOwnersApplication())
      ->getPHID();

    $xactions = array();
    if ($auto_subscribe) {
      $xactions[] = $object->getApplicationTransactionTemplate()
        ->setAuthorPHID($owners_phid)
        ->setTransactionType(PhabricatorTransactions::TYPE_SUBSCRIBERS)
        ->setNewValue(
          array(
            '+' => mpull($auto_subscribe, 'getPHID'),
          ));
    }

    $specs = array(
      array($auto_review, false),
      array($auto_block, true),
    );

    foreach ($specs as $spec) {
      list($reviewers, $blocking) = $spec;
      if (!$reviewers) {
        continue;
      }

      $phids = mpull($reviewers, 'getPHID');
      $xaction = $this->newAutoReviewTransaction($object, $phids, $blocking);
      if ($xaction) {
        $xactions[] = $xaction;
      }
    }

    return $xactions;
  }

  private function newAutoReviewTransaction(
    PhabricatorLiskDAO $object,
    array $phids,
    $is_blocking) {

    // TODO: This is substantially similar to DifferentialReviewersHeraldAction
    // and both are needlessly complex. This logic should live in the normal
    // transaction application pipeline. See T10967.

    $reviewers = $object->getReviewerStatus();
    $reviewers = mpull($reviewers, null, 'getReviewerPHID');

    if ($is_blocking) {
      $new_status = DifferentialReviewerStatus::STATUS_BLOCKING;
    } else {
      $new_status = DifferentialReviewerStatus::STATUS_ADDED;
    }

    $new_strength = DifferentialReviewerStatus::getStatusStrength(
      $new_status);

    $current = array();
    foreach ($phids as $phid) {
      if (!isset($reviewers[$phid])) {
        continue;
      }

      // If we're applying a stronger status (usually, upgrading a reviewer
      // into a blocking reviewer), skip this check so we apply the change.
      $old_strength = DifferentialReviewerStatus::getStatusStrength(
        $reviewers[$phid]->getStatus());
      if ($old_strength <= $new_strength) {
        continue;
      }

      $current[] = $phid;
    }

    $phids = array_diff($phids, $current);

    if (!$phids) {
      return null;
    }

    $phids = array_fuse($phids);

    $value = array();
    foreach ($phids as $phid) {
      $value[$phid] = array(
        'data' => array(
          'status' => $new_status,
        ),
      );
    }

    $edgetype_reviewer = DifferentialRevisionHasReviewerEdgeType::EDGECONST;

    $owners_phid = id(new PhabricatorOwnersApplication())
      ->getPHID();

    return $object->getApplicationTransactionTemplate()
      ->setAuthorPHID($owners_phid)
      ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
      ->setMetadataValue('edge:type', $edgetype_reviewer)
      ->setNewValue(
        array(
          '+' => $value,
        ));
  }

  protected function buildHeraldAdapter(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $revision = id(new DifferentialRevisionQuery())
      ->setViewer($this->getActor())
      ->withPHIDs(array($object->getPHID()))
      ->needActiveDiffs(true)
      ->needReviewerStatus(true)
      ->executeOne();
    if (!$revision) {
      throw new Exception(
        pht('Failed to load revision for Herald adapter construction!'));
    }

    $adapter = HeraldDifferentialRevisionAdapter::newLegacyAdapter(
      $revision,
      $revision->getActiveDiff());

    return $adapter;
  }

  /**
   * Update the table which links Differential revisions to paths they affect,
   * so Diffusion can efficiently find pending revisions for a given file.
   */
  private function updateAffectedPathTable(
    DifferentialRevision $revision,
    DifferentialDiff $diff) {

    $repository = $revision->getRepository();
    if (!$repository) {
      // The repository where the code lives is untracked.
      return;
    }

    $path_prefix = null;

    $local_root = $diff->getSourceControlPath();
    if ($local_root) {
      // We're in a working copy which supports subdirectory checkouts (e.g.,
      // SVN) so we need to figure out what prefix we should add to each path
      // (e.g., trunk/projects/example/) to get the absolute path from the
      // root of the repository. DVCS systems like Git and Mercurial are not
      // affected.

      // Normalize both paths and check if the repository root is a prefix of
      // the local root. If so, throw it away. Note that this correctly handles
      // the case where the remote path is "/".
      $local_root = id(new PhutilURI($local_root))->getPath();
      $local_root = rtrim($local_root, '/');

      $repo_root = id(new PhutilURI($repository->getRemoteURI()))->getPath();
      $repo_root = rtrim($repo_root, '/');

      if (!strncmp($repo_root, $local_root, strlen($repo_root))) {
        $path_prefix = substr($local_root, strlen($repo_root));
      }
    }

    $changesets = $diff->getChangesets();
    $paths = array();
    foreach ($changesets as $changeset) {
      $paths[] = $path_prefix.'/'.$changeset->getFilename();
    }

    // Save the affected paths; we'll use them later to query Owners. This
    // uses the un-expanded paths.
    $this->affectedPaths = $paths;

    // Mark this as also touching all parent paths, so you can see all pending
    // changes to any file within a directory.
    $all_paths = array();
    foreach ($paths as $local) {
      foreach (DiffusionPathIDQuery::expandPathToRoot($local) as $path) {
        $all_paths[$path] = true;
      }
    }
    $all_paths = array_keys($all_paths);

    $path_ids =
      PhabricatorRepositoryCommitChangeParserWorker::lookupOrCreatePaths(
        $all_paths);

    $table = new DifferentialAffectedPath();
    $conn_w = $table->establishConnection('w');

    $sql = array();
    foreach ($path_ids as $path_id) {
      $sql[] = qsprintf(
        $conn_w,
        '(%d, %d, %d, %d)',
        $repository->getID(),
        $path_id,
        time(),
        $revision->getID());
    }

    queryfx(
      $conn_w,
      'DELETE FROM %T WHERE revisionID = %d',
      $table->getTableName(),
      $revision->getID());
    foreach (array_chunk($sql, 256) as $chunk) {
      queryfx(
        $conn_w,
        'INSERT INTO %T (repositoryID, pathID, epoch, revisionID) VALUES %Q',
        $table->getTableName(),
        implode(', ', $chunk));
    }
  }

  /**
   * Update the table connecting revisions to DVCS local hashes, so we can
   * identify revisions by commit/tree hashes.
   */
  private function updateRevisionHashTable(
    DifferentialRevision $revision,
    DifferentialDiff $diff) {

    $vcs = $diff->getSourceControlSystem();
    if ($vcs == DifferentialRevisionControlSystem::SVN) {
      // Subversion has no local commit or tree hash information, so we don't
      // have to do anything.
      return;
    }

    $property = id(new DifferentialDiffProperty())->loadOneWhere(
      'diffID = %d AND name = %s',
      $diff->getID(),
      'local:commits');
    if (!$property) {
      return;
    }

    $hashes = array();

    $data = $property->getData();
    switch ($vcs) {
      case DifferentialRevisionControlSystem::GIT:
        foreach ($data as $commit) {
          $hashes[] = array(
            ArcanistDifferentialRevisionHash::HASH_GIT_COMMIT,
            $commit['commit'],
          );
          $hashes[] = array(
            ArcanistDifferentialRevisionHash::HASH_GIT_TREE,
            $commit['tree'],
          );
        }
        break;
      case DifferentialRevisionControlSystem::MERCURIAL:
        foreach ($data as $commit) {
          $hashes[] = array(
            ArcanistDifferentialRevisionHash::HASH_MERCURIAL_COMMIT,
            $commit['rev'],
          );
        }
        break;
    }

    $conn_w = $revision->establishConnection('w');

    $sql = array();
    foreach ($hashes as $info) {
      list($type, $hash) = $info;
      $sql[] = qsprintf(
        $conn_w,
        '(%d, %s, %s)',
        $revision->getID(),
        $type,
        $hash);
    }

    queryfx(
      $conn_w,
      'DELETE FROM %T WHERE revisionID = %d',
      ArcanistDifferentialRevisionHash::TABLE_NAME,
      $revision->getID());

    if ($sql) {
      queryfx(
        $conn_w,
        'INSERT INTO %T (revisionID, type, hash) VALUES %Q',
        ArcanistDifferentialRevisionHash::TABLE_NAME,
        implode(', ', $sql));
    }
  }

  private function renderAffectedFilesForMail(DifferentialDiff $diff) {
    $changesets = $diff->getChangesets();

    $filenames = mpull($changesets, 'getDisplayFilename');
    sort($filenames);

    $count = count($filenames);
    $max = 250;
    if ($count > $max) {
      $filenames = array_slice($filenames, 0, $max);
      $filenames[] = pht('(%d more files...)', ($count - $max));
    }

    return implode("\n", $filenames);
  }

  private function renderPatchHTMLForMail($patch) {
    return phutil_tag('pre',
      array('style' => 'font-family: monospace;'), $patch);
  }

  private function buildPatchForMail(DifferentialDiff $diff) {
    $format = PhabricatorEnv::getEnvConfig('metamta.differential.patch-format');

    return id(new DifferentialRawDiffRenderer())
      ->setViewer($this->getActor())
      ->setFormat($format)
      ->setChangesets($diff->getChangesets())
      ->buildPatch();
  }

  protected function willPublish(PhabricatorLiskDAO $object, array $xactions) {
    // Reload to pick up the active diff and reviewer status.
    return id(new DifferentialRevisionQuery())
      ->setViewer($this->getActor())
      ->needReviewerStatus(true)
      ->needActiveDiffs(true)
      ->withIDs(array($object->getID()))
      ->executeOne();
  }

  protected function getCustomWorkerState() {
    return array(
      'changedPriorToCommitURI' => $this->changedPriorToCommitURI,
    );
  }

  protected function loadCustomWorkerState(array $state) {
    $this->changedPriorToCommitURI = idx($state, 'changedPriorToCommitURI');
    return $this;
  }

}
