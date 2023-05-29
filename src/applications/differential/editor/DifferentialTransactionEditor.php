<?php

final class DifferentialTransactionEditor
  extends PhabricatorApplicationTransactionEditor {

  private $changedPriorToCommitURI;
  private $isCloseByCommit;
  private $repositoryPHIDOverride = false;
  private $didExpandInlineState = false;
  private $firstBroadcast = false;
  private $wasBroadcasting;
  private $isDraftDemotion;

  private $ownersDiff;
  private $ownersChangesets;

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
    $type_update = DifferentialRevisionUpdateTransaction::TRANSACTIONTYPE;

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

    return $types;
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case DifferentialTransaction::TYPE_INLINE:
        return null;
    }

    return parent::getCustomTransactionOldValue($object, $xaction);
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
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
        $comment = $xaction->getComment();

        $comment->setAttribute('editing', false);

        PhabricatorVersionedDraft::purgeDrafts(
          $comment->getPHID(),
          $this->getActingAsPHID());
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
        case DifferentialRevisionPlanChangesTransaction::TRANSACTIONTYPE:
          if ($xaction->getMetadataValue('draft.demote')) {
            $this->isDraftDemotion = true;
          }
          break;
      }
    }

    $this->wasBroadcasting = $object->getShouldBroadcast();

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

    $want_downgrade = array();
    $must_downgrade = array();
    if ($this->getIsCloseByCommit()) {
      // Never downgrade reviewers when we're closing a revision after a
      // commit.
    } else {
      switch ($xaction->getTransactionType()) {
        case DifferentialRevisionUpdateTransaction::TRANSACTIONTYPE:
          $want_downgrade[] = DifferentialReviewerStatus::STATUS_ACCEPTED;
          $want_downgrade[] = DifferentialReviewerStatus::STATUS_REJECTED;
          break;
        case DifferentialRevisionRequestReviewTransaction::TRANSACTIONTYPE:
          if (!$object->isChangePlanned()) {
            // If the old state isn't "Changes Planned", downgrade the accepts
            // even if they're sticky.

            // We don't downgrade for "Changes Planned" to allow an author to
            // undo a "Plan Changes" by immediately following it up with a
            // "Request Review".
            $want_downgrade[] = DifferentialReviewerStatus::STATUS_ACCEPTED;
            $must_downgrade[] = DifferentialReviewerStatus::STATUS_ACCEPTED;
          }
          $want_downgrade[] = DifferentialReviewerStatus::STATUS_REJECTED;
          break;
      }
    }

    if ($want_downgrade) {
      $void_type = DifferentialRevisionVoidTransaction::TRANSACTIONTYPE;

      $results[] = id(new DifferentialTransaction())
        ->setTransactionType($void_type)
        ->setIgnoreOnNoEffect(true)
        ->setMetadataValue('void.force', $must_downgrade)
        ->setNewValue($want_downgrade);
    }

    $new_author_phid = null;
    switch ($xaction->getTransactionType()) {
      case DifferentialRevisionUpdateTransaction::TRANSACTIONTYPE:
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
          if ($branch !== null && preg_match('/^T(\d+)/i', $branch, $match)) {
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
        $new_author_phid = $actor_phid;
        break;

      case DifferentialRevisionAuthorTransaction::TRANSACTIONTYPE:
        $new_author_phid = $xaction->getNewValue();
        break;

    }

    if ($new_author_phid) {
      $swap_xaction = $this->newSwapReviewersTransaction(
        $object,
        $new_author_phid);
      if ($swap_xaction) {
        $results[] = $swap_xaction;
      }
    }

    if (!$this->didExpandInlineState) {
      switch ($xaction->getTransactionType()) {
        case PhabricatorTransactions::TYPE_COMMENT:
        case DifferentialRevisionUpdateTransaction::TRANSACTIONTYPE:
        case DifferentialTransaction::TYPE_INLINE:
          $this->didExpandInlineState = true;

          $query_template = id(new DifferentialDiffInlineCommentQuery())
            ->withRevisionPHIDs(array($object->getPHID()));

          $state_xaction = $this->newInlineStateTransaction(
            $object,
            $query_template);

          if ($state_xaction) {
            $results[] = $state_xaction;
          }
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

    $active_diff = $new_revision->getActiveDiff();
    $new_diff_phid = $active_diff->getPHID();

    $object->attachReviewers($new_revision->getReviewers());
    $object->attachActiveDiff($active_diff);
    $object->attachRepository($new_revision->getRepository());

    $has_new_diff = false;
    $should_index_paths = false;
    $should_index_hashes = false;
    $need_changesets = false;

    foreach ($xactions as $xaction) {
      switch ($xaction->getTransactionType()) {
        case DifferentialRevisionUpdateTransaction::TRANSACTIONTYPE:
          $need_changesets = true;

          $new_diff_phid = $xaction->getNewValue();
          $has_new_diff = true;

          $should_index_paths = true;
          $should_index_hashes = true;
          break;
        case DifferentialRevisionRepositoryTransaction::TRANSACTIONTYPE:
          // The "AffectedPath" table denormalizes the repository, so we
          // want to update the index if the repository changes.

          $need_changesets = true;

          $should_index_paths = true;
          break;
      }
    }

    if ($need_changesets) {
      $new_diff = $this->requireDiff($new_diff_phid, true);

      if ($should_index_paths) {
        id(new DifferentialAffectedPathEngine())
          ->setRevision($object)
          ->setDiff($new_diff)
          ->updateAffectedPaths();
      }

      if ($should_index_hashes) {
        $this->updateRevisionHashTable($object, $new_diff);
      }

      if ($has_new_diff) {
        $this->ownersDiff = $new_diff;
        $this->ownersChangesets = $new_diff->getChangesets();
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
    } else if ($was_revision) {
      // This revision was "Needs Revision", but no longer has any rejecting
      // reviewers. This usually happens after the last rejecting reviewer
      // resigns or is removed. Put the revision back in "Needs Review".
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

    if (!$object->getShouldBroadcast()) {
      return false;
    }

    return true;
  }

  protected function shouldSendMail(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return true;
  }

  protected function getMailTo(PhabricatorLiskDAO $object) {
    if ($object->getShouldBroadcast()) {
      $this->requireReviewers($object);

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

    // If we're demoting a draft after a build failure, just notify the author.
    if ($this->isDraftDemotion) {
      $author_phid = $object->getAuthorPHID();
      return array(
        $author_phid,
      );
    }

    return array();
  }

  protected function getMailCC(PhabricatorLiskDAO $object) {
    if (!$object->getShouldBroadcast()) {
      return array();
    }

    return parent::getMailCC($object);
  }

  protected function newMailUnexpandablePHIDs(PhabricatorLiskDAO $object) {
    $this->requireReviewers($object);

    $phids = array();

    foreach ($object->getReviewers() as $reviewer) {
      if ($reviewer->isResigned()) {
        $phids[] = $reviewer->getReviewerPHID();
      }
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
      $type_update = DifferentialRevisionUpdateTransaction::TRANSACTIONTYPE;
      if ($strongest->getTransactionType() == $type_update) {
        $show_lines = true;
      }
    }

    if ($show_lines) {
      $count = new PhutilNumber($object->getLineCount());
      $action = pht('%s] [%s', $action, $object->getRevisionScaleGlyphs());
    }

    return $action;
  }

  protected function getMailSubjectPrefix() {
    return pht('[Differential]');
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
    $monogram = $object->getMonogram();
    $title = $object->getTitle();

    return id(new PhabricatorMetaMTAMail())
      ->setSubject(pht('%s: %s', $monogram, $title))
      ->setMustEncryptSubject(pht('%s: Revision Updated', $monogram))
      ->setMustEncryptURI($object->getURI());
  }

  protected function getTransactionsForMail(
    PhabricatorLiskDAO $object,
    array $xactions) {
    // If this is the first time we're sending mail about this revision, we
    // generate mail for all prior transactions, not just whatever is being
    // applied now. This gets the "added reviewers" lines and other relevant
    // information into the mail.
    if ($this->isFirstBroadcast()) {
      return $this->loadUnbroadcastTransactions($object);
    }

    return $xactions;
  }

  protected function getObjectLinkButtonLabelForMail(
    PhabricatorLiskDAO $object) {
    return pht('View Revision');
  }

  protected function buildMailBody(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $viewer = $this->requireActor();

    $body = id(new PhabricatorMetaMTAMailBody())
      ->setViewer($viewer);

    $revision_uri = $this->getObjectLinkButtonURIForMail($object);
    $new_uri = $revision_uri.'/new/';

    $this->addHeadersAndCommentsToMailBody(
      $body,
      $xactions,
      $this->getObjectLinkButtonLabelForMail($object),
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

    $update_xaction = null;
    foreach ($xactions as $xaction) {
      switch ($xaction->getTransactionType()) {
        case DifferentialRevisionUpdateTransaction::TRANSACTIONTYPE:
          $update_xaction = $xaction;
          break;
      }
    }

    if ($update_xaction) {
      $diff = $this->requireDiff($update_xaction->getNewValue(), true);
    } else {
      $diff = null;
    }

    $changed_uri = $this->getChangedPriorToCommitURI();
    if ($changed_uri) {
      $body->addLinkSection(
        pht('CHANGED PRIOR TO COMMIT'),
        $changed_uri);
    }

    $this->addCustomFieldsToMailBody($body, $object, $xactions);

    if (!$this->isFirstBroadcast()) {
      $body->addLinkSection(pht('CHANGES SINCE LAST ACTION'), $new_uri);
    }

    $body->addLinkSection(
      pht('REVISION DETAIL'),
      $revision_uri);

    if ($update_xaction) {
      $body->addTextSection(
        pht('AFFECTED FILES'),
        $this->renderAffectedFilesForMail($diff));

      $config_key_inline = 'metamta.differential.inline-patches';
      $config_inline = PhabricatorEnv::getEnvConfig($config_key_inline);

      $config_key_attach = 'metamta.differential.attach-patches';
      $config_attach = PhabricatorEnv::getEnvConfig($config_key_attach);

      if ($config_inline || $config_attach) {
        $body_limit = PhabricatorEnv::getEnvConfig('metamta.email-body-limit');

        try {
          $patch = $this->buildPatchForMail($diff, $body_limit);
        } catch (ArcanistDiffByteSizeException $ex) {
          $patch = null;
        }

        if (($patch !== null) && $config_inline) {
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

        if (($patch !== null) && $config_attach) {
          // See T12033, T11767, and PHI55. This is a crude fix to stop the
          // major concrete problems that lackluster email size limits cause.
          if (strlen($patch) < $body_limit) {
            $name = pht('D%s.%s.patch', $object->getID(), $diff->getID());
            $mime_type = 'text/x-patch; charset=utf-8';
            $body->addAttachment(
              new PhabricatorMailAttachment($patch, $name, $mime_type));
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

    $revert_refs = id(new DifferentialCustomFieldRevertsParser())
      ->parseCorpus($content_block);

    $revert_monograms = array();
    foreach ($revert_refs as $match) {
      foreach ($match['monograms'] as $monogram) {
        $revert_monograms[] = $monogram;
      }
    }

    if ($revert_monograms) {
      $revert_objects = DiffusionCommitRevisionQuery::loadRevertedObjects(
        $this->getActor(),
        $object,
        $revert_monograms,
        null);

      $revert_phids = mpull($revert_objects, 'getPHID', 'getPHID');

      $revert_type = DiffusionCommitRevertsCommitEdgeType::EDGECONST;
      $edges[$revert_type] = $revert_phids;
    } else {
      $revert_phids = array();
    }

    $this->addUnmentionablePHIDs($task_phids);
    $this->addUnmentionablePHIDs($rev_phids);
    $this->addUnmentionablePHIDs($revert_phids);

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

    $limit = 100;
    $limit_note = null;
    if (count($inlines) > $limit) {
      $limit_note = pht(
        '(Showing first %s of %s inline comments.)',
        new PhutilNumber($limit),
        phutil_count($inlines));

      $inlines = array_slice($inlines, 0, $limit, true);
    }

    $section = id(new DifferentialInlineCommentMailView())
      ->setViewer($this->getActor())
      ->setInlines($inlines)
      ->buildMailSection();

    $header = pht('INLINE COMMENTS');

    $section_text = "\n".$section->getPlaintext();
    if ($limit_note) {
      $section_text = $limit_note."\n".$section_text;
    }

    $style = array(
      'margin: 6px 0 12px 0;',
    );

    $section_html = phutil_tag(
      'div',
      array(
        'style' => implode(' ', $style),
      ),
      $section->getHTML());

    if ($limit_note) {
      $section_html = array(
        phutil_tag(
          'em',
          array(),
          $limit_note),
        $section_html,
      );
    }

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

  public function requireDiff($phid, $need_changesets = false) {
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

    $diff = $this->ownersDiff;
    $changesets = $this->ownersChangesets;

    $this->ownersDiff = null;
    $this->ownersChangesets = null;

    if (!$changesets) {
      return array();
    }

    $packages = PhabricatorOwnersPackage::loadAffectedPackagesForChangesets(
      $repository,
      $diff,
      $changesets);
    if (!$packages) {
      return array();
    }

    // Identify the packages with "Non-Owner Author" review rules and remove
    // them if the author has authority over the package.

    $autoreview_map = PhabricatorOwnersPackage::getAutoreviewOptionsMap();
    $need_authority = array();
    foreach ($packages as $package) {
      $autoreview_setting = $package->getAutoReview();

      $spec = idx($autoreview_map, $autoreview_setting);
      if (!$spec) {
        continue;
      }

      if (idx($spec, 'authority')) {
        $need_authority[$package->getPHID()] = $package->getPHID();
      }
    }

    if ($need_authority) {
      $authority = id(new PhabricatorOwnersPackageQuery())
        ->setViewer(PhabricatorUser::getOmnipotentUser())
        ->withPHIDs($need_authority)
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
    }

    $auto_subscribe = array();
    $auto_review = array();
    $auto_block = array();

    foreach ($packages as $package) {
      switch ($package->getAutoReview()) {
        case PhabricatorOwnersPackage::AUTOREVIEW_REVIEW:
        case PhabricatorOwnersPackage::AUTOREVIEW_REVIEW_ALWAYS:
          $auto_review[] = $package;
          break;
        case PhabricatorOwnersPackage::AUTOREVIEW_BLOCK:
        case PhabricatorOwnersPackage::AUTOREVIEW_BLOCK_ALWAYS:
          $auto_block[] = $package;
          break;
        case PhabricatorOwnersPackage::AUTOREVIEW_SUBSCRIBE:
        case PhabricatorOwnersPackage::AUTOREVIEW_SUBSCRIBE_ALWAYS:
          $auto_subscribe[] = $package;
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
    if (!$object->getShouldBroadcast()) {
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

    $type_update = DifferentialRevisionUpdateTransaction::TRANSACTIONTYPE;
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
        'INSERT INTO %T (revisionID, type, hash) VALUES %LQ',
        ArcanistDifferentialRevisionHash::TABLE_NAME,
        $sql);
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

  private function buildPatchForMail(DifferentialDiff $diff, $byte_limit) {
    $format = PhabricatorEnv::getEnvConfig('metamta.differential.patch-format');

    return id(new DifferentialRawDiffRenderer())
      ->setViewer($this->getActor())
      ->setFormat($format)
      ->setChangesets($diff->getChangesets())
      ->setByteLimit($byte_limit)
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
      'isDraftDemotion' => $this->isDraftDemotion,
    );
  }

  protected function loadCustomWorkerState(array $state) {
    $this->changedPriorToCommitURI = idx($state, 'changedPriorToCommitURI');
    $this->firstBroadcast = idx($state, 'firstBroadcast');
    $this->isDraftDemotion = idx($state, 'isDraftDemotion');
    return $this;
  }

  private function newSwapReviewersTransaction(
    DifferentialRevision $revision,
    $new_author_phid) {

    $old_author_phid = $revision->getAuthorPHID();

    if ($old_author_phid === $new_author_phid) {
      return;
    }

    // If the revision is changing authorship, add the previous author as a
    // reviewer and remove the new author.

    $edits = array(
      '-' => array(
        $new_author_phid,
      ),
      '+' => array(
        $old_author_phid,
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
    // In a moment, we're going to try to publish draft revisions which have
    // completed all their builds. However, we only want to do that if the
    // actor is either the revision author or an omnipotent user (generally,
    // the Harbormaster application).

    // If we let any actor publish the revision as a side effect of other
    // changes then an unlucky third party who innocently comments on the draft
    // can end up racing Harbormaster and promoting the revision. At best, this
    // is confusing. It can also run into validation problems with the "Request
    // Review" transaction. See PHI309 for some discussion.
    $author_phid = $object->getAuthorPHID();
    $viewer = $this->requireActor();
    $can_undraft =
      ($this->getActingAsPHID() === $author_phid) ||
      ($viewer->isOmnipotent());

    // If a draft revision has no outstanding builds and we're automatically
    // making drafts public after builds finish, make the revision public.
    if ($can_undraft) {
      $auto_undraft = !$object->getHoldAsDraft();
    } else {
      $auto_undraft = false;
    }

    $can_promote = false;
    $can_demote = false;

    // "Draft" revisions can promote to "Review Requested" after builds pass,
    // or demote to "Changes Planned" after builds fail.
    if ($object->isDraft()) {
      $can_promote = true;
      $can_demote = true;
    }

    // See PHI584. "Changes Planned" revisions which are not yet broadcasting
    // can promote to "Review Requested" if builds pass.

    // This pass is presumably the result of someone restarting the builds and
    // having them work this time, perhaps because the builds are not perfectly
    // reliable or perhaps because someone fixed some issue with build hardware
    // or some other dependency.

    // Currently, there's no legitimate way to end up in this state except
    // through automatic demotion, so this behavior should not generate an
    // undue level of confusion or ambiguity. Also note that these changes can
    // not demote again since they've already been demoted once.
    if ($object->isChangePlanned()) {
      if (!$object->getShouldBroadcast()) {
        $can_promote = true;
      }
    }

    if (($can_promote || $can_demote) && $auto_undraft) {
      $status = $this->loadCompletedBuildableStatus($object);

      $is_passed = ($status === HarbormasterBuildableStatus::STATUS_PASSED);
      $is_failed = ($status === HarbormasterBuildableStatus::STATUS_FAILED);

      if ($is_passed && $can_promote) {
        // When Harbormaster moves a revision out of the draft state, we
        // attribute the action to the revision author since this is more
        // natural and more useful.

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
      } else if ($is_failed && $can_demote) {
        // When demoting a revision, we act as "Harbormaster" instead of
        // the author since this feels a little more natural.
        $harbormaster_phid = id(new PhabricatorHarbormasterApplication())
          ->getPHID();

        $xaction = $object->getApplicationTransactionTemplate()
          ->setAuthorPHID($harbormaster_phid)
          ->setMetadataValue('draft.demote', true)
          ->setTransactionType(
            DifferentialRevisionPlanChangesTransaction::TRANSACTIONTYPE)
          ->setNewValue(true);

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
    $was_broadcasting = $this->wasBroadcasting;

    if ($object->getShouldBroadcast()) {
      if (!$was_broadcasting || $is_new) {
        // Mark this as the first broadcast we're sending about the revision
        // so mail can generate specially.
        $this->firstBroadcast = true;
      }
    }

    return $xactions;
  }

  private function loadCompletedBuildableStatus(
    DifferentialRevision $revision) {
    $viewer = $this->requireActor();
    $builds = $revision->loadImpactfulBuilds($viewer);
    return $revision->newBuildableStatusForBuilds($builds);
  }

  private function requireReviewers(DifferentialRevision $revision) {
    if ($revision->hasAttachedReviewers()) {
      return;
    }

    $with_reviewers = id(new DifferentialRevisionQuery())
      ->setViewer($this->getActor())
      ->needReviewers(true)
      ->withPHIDs(array($revision->getPHID()))
      ->executeOne();
    if (!$with_reviewers) {
      throw new Exception(
        pht(
          'Failed to reload revision ("%s").',
          $revision->getPHID()));
    }

    $revision->attachReviewers($with_reviewers->getReviewers());
  }


}
