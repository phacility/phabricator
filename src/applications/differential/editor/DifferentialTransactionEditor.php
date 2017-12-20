<?php

final class DifferentialTransactionEditor
  extends PhabricatorApplicationTransactionEditor {

  private $changedPriorToCommitURI;
  private $isCloseByCommit;
  private $repositoryPHIDOverride = false;
  private $didExpandInlineState = false;
  private $hasReviewTransaction = false;
  private $affectedPaths;
  private $firstBroadcast = false;
  private $wasDraft = false;

  public function getEditorApplicationClass() {
    return 'PhabricatorDifferentialApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Differential Revisions');
  }

  public function getCreateObjectTitle($author, $object) {
    return pht('%s created this revision.', $author);
  }

  public function getCreateObjectTitleForFeed($author, $object) {
    return pht('%s created %s.', $author, $object);
  }

  public function isFirstBroadcast() {
    return $this->firstBroadcast;
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
    $types[] = PhabricatorTransactions::TYPE_INLINESTATE;

    $types[] = DifferentialTransaction::TYPE_INLINE;
    $types[] = DifferentialTransaction::TYPE_UPDATE;

    return $types;
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
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
      case DifferentialTransaction::TYPE_UPDATE:
        return $xaction->getNewValue();
      case DifferentialTransaction::TYPE_INLINE:
        return null;
    }

    return parent::getCustomTransactionNewValue($object, $xaction);
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case DifferentialTransaction::TYPE_INLINE:
        return;
      case DifferentialTransaction::TYPE_UPDATE:
        if (!$this->getIsCloseByCommit()) {
          if ($object->isNeedsRevision() ||
              $object->isChangePlanned() ||
              $object->isAbandoned()) {
            $object->setModernRevisionStatus(
              DifferentialRevisionStatus::NEEDS_REVIEW);
          }
        }

        $diff = $this->requireDiff($xaction->getNewValue());

        $this->updateRevisionLineCounts($object, $diff);

        if ($this->repositoryPHIDOverride !== false) {
          $object->setRepositoryPHID($this->repositoryPHIDOverride);
        } else {
          $object->setRepositoryPHID($diff->getRepositoryPHID());
        }

        $object->attachActiveDiff($diff);
        $object->setActiveDiffPHID($diff->getPHID());
        return;
    }

    return parent::applyCustomInternalTransaction($object, $xaction);
  }

  protected function expandTransactions(
    PhabricatorLiskDAO $object,
    array $xactions) {

    foreach ($xactions as $xaction) {
      switch ($xaction->getTransactionType()) {
        case PhabricatorTransactions::TYPE_INLINESTATE:
          // If we have an "Inline State" transaction already, the caller
          // built it for us so we don't need to expand it again.
          $this->didExpandInlineState = true;
          break;
        case DifferentialRevisionAcceptTransaction::TRANSACTIONTYPE:
        case DifferentialRevisionRejectTransaction::TRANSACTIONTYPE:
        case DifferentialRevisionResignTransaction::TRANSACTIONTYPE:
          // If we have a review transaction, we'll skip marking the user
          // as "Commented" later. This should get cleaner after T10967.
          $this->hasReviewTransaction = true;
          break;
      }
    }

    $this->wasDraft = $object->isDraft();

    return parent::expandTransactions($object, $xactions);
  }

  protected function expandTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    $results = parent::expandTransaction($object, $xaction);

    $actor = $this->getActor();
    $actor_phid = $this->getActingAsPHID();
    $type_edge = PhabricatorTransactions::TYPE_EDGE;

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
        case DifferentialRevisionRequestReviewTransaction::TRANSACTIONTYPE:
          $downgrade_rejects = true;
          if ((!$is_sticky_accept) ||
              (!$object->isChangePlanned())) {
            // If the old state isn't "changes planned", downgrade the accepts.
            // This exception allows an accepted revision to go through
            // "Plan Changes" -> "Request Review" and return to "accepted" if
            // the author didn't update the revision, essentially undoing the
            // "Plan Changes".
            $downgrade_accepts = true;
          }
          break;
      }
    }

    $new_accept = DifferentialReviewerStatus::STATUS_ACCEPTED;
    $new_reject = DifferentialReviewerStatus::STATUS_REJECTED;
    $old_accept = DifferentialReviewerStatus::STATUS_ACCEPTED_OLDER;
    $old_reject = DifferentialReviewerStatus::STATUS_REJECTED_OLDER;

    $downgrade = array();
    if ($downgrade_accepts) {
      $downgrade[] = DifferentialReviewerStatus::STATUS_ACCEPTED;
    }

    if ($downgrade_rejects) {
      $downgrade[] = DifferentialReviewerStatus::STATUS_REJECTED;
    }

    if ($downgrade) {
      $void_type = DifferentialRevisionVoidTransaction::TRANSACTIONTYPE;

      $results[] = id(new DifferentialTransaction())
        ->setTransactionType($void_type)
        ->setIgnoreOnNoEffect(true)
        ->setNewValue($downgrade);
    }

    $is_commandeer = false;
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

      case DifferentialRevisionCommandeerTransaction::TRANSACTIONTYPE:
        $is_commandeer = true;
        break;
    }

    if ($is_commandeer) {
      $results[] = $this->newCommandeerReviewerTransaction($object);
    }

    if (!$this->didExpandInlineState) {
      switch ($xaction->getTransactionType()) {
        case PhabricatorTransactions::TYPE_COMMENT:
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
      ->needReviewers(true)
      ->needActiveDiffs(true)
      ->withIDs(array($object->getID()))
      ->executeOne();
    if (!$new_revision) {
      throw new Exception(
        pht('Failed to load revision from transaction finalization.'));
    }

    $object->attachReviewers($new_revision->getReviewers());
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

    $xactions = $this->updateReviewStatus($object, $xactions);
    $this->markReviewerComments($object, $xactions);

    return $xactions;
  }

  private function updateReviewStatus(
    DifferentialRevision $revision,
    array $xactions) {

    $was_accepted = $revision->isAccepted();
    $was_revision = $revision->isNeedsRevision();
    $was_review = $revision->isNeedsReview();
    if (!$was_accepted && !$was_revision && !$was_review) {
      // Revisions can't transition out of other statuses (like closed or
      // abandoned) as a side effect of reviewer status changes.
      return $xactions;
    }

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

    $active_diff = $revision->getActiveDiff();
    foreach ($revision->getReviewers() as $reviewer) {
      $reviewer_status = $reviewer->getReviewerStatus();
      switch ($reviewer_status) {
        case DifferentialReviewerStatus::STATUS_REJECTED:
          $active_phid = $active_diff->getPHID();
          if ($reviewer->isRejected($active_phid)) {
            $has_rejecting_reviewer = true;
          } else {
            $has_rejecting_older_reviewer = true;
          }
          break;
        case DifferentialReviewerStatus::STATUS_REJECTED_OLDER:
          $has_rejecting_older_reviewer = true;
          break;
        case DifferentialReviewerStatus::STATUS_BLOCKING:
          $has_blocking_reviewer = true;
          break;
        case DifferentialReviewerStatus::STATUS_ACCEPTED:
          if ($reviewer->isUser()) {
            $active_phid = $active_diff->getPHID();
            if ($reviewer->isAccepted($active_phid)) {
              $has_accepting_user = true;
            }
          }
          break;
      }
    }

    $new_status = null;
    if ($has_accepting_user &&
        !$has_rejecting_reviewer &&
        !$has_rejecting_older_reviewer &&
        !$has_blocking_reviewer) {
      $new_status = DifferentialRevisionStatus::ACCEPTED;
    } else if ($has_rejecting_reviewer) {
      // This isn't accepted, and there's at least one rejecting reviewer,
      // so the revision needs changes. This usually happens after a
      // "reject".
      $new_status = DifferentialRevisionStatus::NEEDS_REVISION;
    } else if ($was_accepted) {
      // This revision was accepted, but it no longer satisfies the
      // conditions for acceptance. This usually happens after an accepting
      // reviewer resigns or is removed.
      $new_status = DifferentialRevisionStatus::NEEDS_REVIEW;
    }

    if ($new_status === null) {
      return $xactions;
    }

    $old_status = $revision->getModernRevisionStatus();
    if ($new_status == $old_status) {
      return $xactions;
    }

    $xaction = id(new DifferentialTransaction())
      ->setTransactionType(
          DifferentialRevisionStatusTransaction::TRANSACTIONTYPE)
      ->setOldValue($old_status)
      ->setNewValue($new_status);

    $xaction = $this->populateTransaction($revision, $xaction)
      ->save();
    $xactions[] = $xaction;

    // Save the status adjustment we made earlier.
    $revision
      ->setModernRevisionStatus($new_status)
      ->save();

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
      }
    }

    return $errors;
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

  protected function shouldPublishFeedStory(
    PhabricatorLiskDAO $object,
    array $xactions) {

    if (!$object->shouldBroadcast()) {
      return false;
    }

    return true;
  }

  protected function shouldSendMail(
    PhabricatorLiskDAO $object,
    array $xactions) {

    if (!$object->shouldBroadcast()) {
      return false;
    }

    return true;
  }

  protected function getMailTo(PhabricatorLiskDAO $object) {
    $phids = array();
    $phids[] = $object->getAuthorPHID();
    foreach ($object->getReviewers() as $reviewer) {
      if ($reviewer->isResigned()) {
        continue;
      }

      $phids[] = $reviewer->getReviewerPHID();
    }
    return $phids;
  }

  protected function getMailAction(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $show_lines = false;
    if ($this->isFirstBroadcast()) {
      $action = pht('Request');

      $show_lines = true;
    } else {
      $action = parent::getMailAction($object, $xactions);

      $strongest = $this->getStrongestAction($object, $xactions);
      $type_update = DifferentialTransaction::TYPE_UPDATE;
      if ($strongest->getTransactionType() == $type_update) {
        $show_lines = true;
      }
    }

    if ($show_lines) {
      $count = new PhutilNumber($object->getLineCount());
      $action = pht('%s, %s line(s)', $action, $count);
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

    $viewer = $this->requireActor();

    // If this is the first time we're sending mail about this revision, we
    // generate mail for all prior transactions, not just whatever is being
    // applied now. This gets the "added reviewers" lines and other relevant
    // information into the mail.
    if ($this->isFirstBroadcast()) {
      $xactions = $this->loadUnbroadcastTransactions($object);
    }

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
          // See T12033, T11767, and PHI55. This is a crude fix to stop the
          // major concrete problems that lackluster email size limits cause.
          if (strlen($patch) < $body_limit) {
            $name = pht('D%s.%s.patch', $object->getID(), $diff->getID());
            $mime_type = 'text/x-patch; charset=utf-8';
            $body->addAttachment(
              new PhabricatorMetaMTAAttachment($patch, $name, $mime_type));
          }
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

    // For "Fixes ..." and "Depends on ...", we're only going to look at
    // content blocks which are part of the revision itself (like "Summary"
    // and  "Test Plan"), not comments.
    $content_parts = array();
    foreach ($changes as $change) {
      if ($change->getTransaction()->isCommentTransaction()) {
        continue;
      }
      $content_parts[] = $change->getNewValue();
    }
    if (!$content_parts) {
      return array();
    }
    $content_block = implode("\n\n", $content_parts);
    $task_map = array();
    $task_refs = id(new ManiphestCustomFieldStatusParser())
      ->parseCorpus($content_block);
    foreach ($task_refs as $match) {
      foreach ($match['monograms'] as $monogram) {
        $task_id = (int)trim($monogram, 'tT');
        $task_map[$task_id] = true;
      }
    }

    $rev_map = array();
    $rev_refs = id(new DifferentialCustomFieldDependsOnParser())
      ->parseCorpus($content_block);
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
    return true;
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

    $reviewers = $object->getReviewers();
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
        $reviewers[$phid]->getReviewerStatus());
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
      if ($is_blocking) {
        $value[] = 'blocking('.$phid.')';
      } else {
        $value[] = $phid;
      }
    }

    $owners_phid = id(new PhabricatorOwnersApplication())
      ->getPHID();

    $reviewers_type = DifferentialRevisionReviewersTransaction::TRANSACTIONTYPE;

    return $object->getApplicationTransactionTemplate()
      ->setAuthorPHID($owners_phid)
      ->setTransactionType($reviewers_type)
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
      ->needReviewers(true)
      ->executeOne();
    if (!$revision) {
      throw new Exception(
        pht('Failed to load revision for Herald adapter construction!'));
    }

    $adapter = HeraldDifferentialRevisionAdapter::newLegacyAdapter(
      $revision,
      $revision->getActiveDiff());

    // If the object is still a draft, prevent "Send me an email" and other
    // similar rules from acting yet.
    if (!$object->shouldBroadcast()) {
      $adapter->setForbiddenAction(
        HeraldMailableState::STATECONST,
        DifferentialHeraldStateReasons::REASON_DRAFT);
    }

    // If this edit didn't actually change the diff (for example, a user
    // edited the title or changed subscribers), prevent "Run build plan"
    // and other similar rules from acting yet, since the build results will
    // not (or, at least, should not) change unless the actual source changes.
    // We also don't run Differential builds if the update was caused by
    // discovering a commit, as the expectation is that Diffusion builds take
    // over once things land.
    $has_update = false;
    $has_commit = false;

    $type_update = DifferentialTransaction::TYPE_UPDATE;
    foreach ($xactions as $xaction) {
      if ($xaction->getTransactionType() != $type_update) {
        continue;
      }

      if ($xaction->getMetadataValue('isCommitUpdate')) {
        $has_commit = true;
      } else {
        $has_update = true;
      }

      break;
    }

    if ($has_commit) {
      $adapter->setForbiddenAction(
        HeraldBuildableState::STATECONST,
        DifferentialHeraldStateReasons::REASON_LANDED);
    } else if (!$has_update) {
      $adapter->setForbiddenAction(
        HeraldBuildableState::STATECONST,
        DifferentialHeraldStateReasons::REASON_UNCHANGED);
    }

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
      ->needReviewers(true)
      ->needActiveDiffs(true)
      ->withIDs(array($object->getID()))
      ->executeOne();
  }

  protected function getCustomWorkerState() {
    return array(
      'changedPriorToCommitURI' => $this->changedPriorToCommitURI,
      'firstBroadcast' => $this->firstBroadcast,
    );
  }

  protected function loadCustomWorkerState(array $state) {
    $this->changedPriorToCommitURI = idx($state, 'changedPriorToCommitURI');
    $this->firstBroadcast = idx($state, 'firstBroadcast');
    return $this;
  }

  private function newCommandeerReviewerTransaction(
    DifferentialRevision $revision) {

    $actor_phid = $this->getActingAsPHID();
    $owner_phid = $revision->getAuthorPHID();

    // If the user is commandeering, add the previous owner as a
    // reviewer and remove the actor.

    $edits = array(
      '-' => array(
        $actor_phid,
      ),
      '+' => array(
        $owner_phid,
      ),
    );

    // NOTE: We're setting setIsCommandeerSideEffect() on this because normally
    // you can't add a revision's author as a reviewer, but this action swaps
    // them after validation executes.

    $xaction_type = DifferentialRevisionReviewersTransaction::TRANSACTIONTYPE;

    return id(new DifferentialTransaction())
      ->setTransactionType($xaction_type)
      ->setIgnoreOnNoEffect(true)
      ->setIsCommandeerSideEffect(true)
      ->setNewValue($edits);
  }

  public function getActiveDiff($object) {
    if ($this->getIsNewObject()) {
      return null;
    } else {
      return $object->getActiveDiff();
    }
  }

  /**
   * When a reviewer makes a comment, mark the last revision they commented
   * on.
   *
   * This allows us to show a hint to help authors and other reviewers quickly
   * distinguish between reviewers who have participated in the discussion and
   * reviewers who haven't been part of it.
   */
  private function markReviewerComments($object, array $xactions) {
    $acting_phid = $this->getActingAsPHID();
    if (!$acting_phid) {
      return;
    }

    $diff = $this->getActiveDiff($object);
    if (!$diff) {
      return;
    }

    $has_comment = false;
    foreach ($xactions as $xaction) {
      if ($xaction->hasComment()) {
        $has_comment = true;
        break;
      }
    }

    if (!$has_comment) {
      return;
    }

    $reviewer_table = new DifferentialReviewer();
    $conn = $reviewer_table->establishConnection('w');

    queryfx(
      $conn,
      'UPDATE %T SET lastCommentDiffPHID = %s
        WHERE revisionPHID = %s
        AND reviewerPHID = %s',
      $reviewer_table->getTableName(),
      $diff->getPHID(),
      $object->getPHID(),
      $acting_phid);
  }

  private function loadUnbroadcastTransactions($object) {
    $viewer = $this->requireActor();

    $xactions = id(new DifferentialTransactionQuery())
      ->setViewer($viewer)
      ->withObjectPHIDs(array($object->getPHID()))
      ->execute();

    return array_reverse($xactions);
  }


  protected function didApplyTransactions($object, array $xactions) {
    // If a draft revision has no outstanding builds and we're automatically
    // making drafts public after builds finish, make the revision public.
    $auto_undraft = !$object->getHoldAsDraft();

    if ($object->isDraft() && $auto_undraft) {
      $active_builds = $this->hasActiveBuilds($object);
      if (!$active_builds) {
        // When Harbormaster moves a revision out of the draft state, we
        // attribute the action to the revision author since this is more
        // natural and more useful.
        $author_phid = $object->getAuthorPHID();

        // Additionally, we change the acting PHID for the transaction set
        // to the author if it isn't already a user so that mail comes from
        // the natural author.
        $acting_phid = $this->getActingAsPHID();
        $user_type = PhabricatorPeopleUserPHIDType::TYPECONST;
        if (phid_get_type($acting_phid) != $user_type) {
          $this->setActingAsPHID($author_phid);
        }

        $xaction = $object->getApplicationTransactionTemplate()
          ->setAuthorPHID($author_phid)
          ->setTransactionType(
            DifferentialRevisionRequestReviewTransaction::TRANSACTIONTYPE)
          ->setNewValue(true);

        // If we're creating this revision and immediately moving it out of
        // the draft state, mark this as a create transaction so it gets
        // hidden in the timeline and mail, since it isn't interesting: it
        // is as though the draft phase never happened.
        if ($this->getIsNewObject()) {
          $xaction->setIsCreateTransaction(true);
        }

        // Queue this transaction and apply it separately after the current
        // batch of transactions finishes so that Herald can fire on the new
        // revision state. See T13027 for discussion.
        $this->queueTransaction($xaction);
      }
    }

    // If the revision is new or was a draft, and is no longer a draft, we
    // might be sending the first email about it.

    // This might mean it was created directly into a non-draft state, or
    // it just automatically undrafted after builds finished, or a user
    // explicitly promoted it out of the draft state with an action like
    // "Request Review".

    // If we haven't sent any email about it yet, mark this email as the first
    // email so the mail gets enriched with "SUMMARY" and "TEST PLAN".

    $is_new = $this->getIsNewObject();
    $was_draft = $this->wasDraft;

    if (!$object->isDraft() && ($was_draft || $is_new)) {
      if (!$object->getHasBroadcast()) {
        // Mark this as the first broadcast we're sending about the revision
        // so mail can generate specially.
        $this->firstBroadcast = true;

        $object
          ->setHasBroadcast(true)
          ->save();
      }
    }

    return $xactions;
  }

  private function hasActiveBuilds($object) {
    $viewer = $this->requireActor();

    $builds = $object->loadActiveBuilds($viewer);
    if (!$builds) {
      return false;
    }

    return true;
  }

  private function updateRevisionLineCounts(
    DifferentialRevision $revision,
    DifferentialDiff $diff) {

    $revision->setLineCount($diff->getLineCount());

    $conn = $revision->establishConnection('r');

    $row = queryfx_one(
      $conn,
      'SELECT SUM(addLines) A, SUM(delLines) D FROM %T
        WHERE diffID = %d',
      id(new DifferentialChangeset())->getTableName(),
      $diff->getID());

    if ($row) {
      $revision->setAddedLineCount((int)$row['A']);
      $revision->setRemovedLineCount((int)$row['D']);
    }
  }

}
