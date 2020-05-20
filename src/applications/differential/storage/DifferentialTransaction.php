<?php

final class DifferentialTransaction
  extends PhabricatorModularTransaction {

  private $isCommandeerSideEffect;

  const TYPE_INLINE  = 'differential:inline';
  const TYPE_ACTION  = 'differential:action';

  const MAILTAG_REVIEWERS      = 'differential-reviewers';
  const MAILTAG_CLOSED         = 'differential-committed';
  const MAILTAG_CC             = 'differential-cc';
  const MAILTAG_COMMENT        = 'differential-comment';
  const MAILTAG_UPDATED        = 'differential-updated';
  const MAILTAG_REVIEW_REQUEST = 'differential-review-request';
  const MAILTAG_OTHER          = 'differential-other';

  public function getBaseTransactionClass() {
    return 'DifferentialRevisionTransactionType';
  }

  protected function newFallbackModularTransactionType() {
    // TODO: This allows us to render modern strings for older transactions
    // without doing a migration. At some point, we should do a migration and
    // throw this away.

    // NOTE: Old reviewer edits are raw edge transactions. They could be
    // migrated to modular transactions when the rest of this migrates.

    $xaction_type = $this->getTransactionType();
    if ($xaction_type == PhabricatorTransactions::TYPE_CUSTOMFIELD) {
      switch ($this->getMetadataValue('customfield:key')) {
        case 'differential:title':
          return new DifferentialRevisionTitleTransaction();
        case 'differential:test-plan':
          return new DifferentialRevisionTestPlanTransaction();
        case 'differential:repository':
          return new DifferentialRevisionRepositoryTransaction();
      }
    }

    return parent::newFallbackModularTransactionType();
  }


  public function setIsCommandeerSideEffect($is_side_effect) {
    $this->isCommandeerSideEffect = $is_side_effect;
    return $this;
  }

  public function getIsCommandeerSideEffect() {
    return $this->isCommandeerSideEffect;
  }

  public function getApplicationName() {
    return 'differential';
  }

  public function getApplicationTransactionType() {
    return DifferentialRevisionPHIDType::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return new DifferentialTransactionComment();
  }

  public function shouldHide() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case DifferentialRevisionRequestReviewTransaction::TRANSACTIONTYPE:
        // Don't hide the initial "X requested review: ..." transaction from
        // mail or feed even when it occurs during creation. We need this
        // transaction to survive so we'll generate mail and feed stories when
        // revisions immediately leave the draft state. See T13035 for
        // discussion.
        return false;
    }

    return parent::shouldHide();
  }

  public function shouldHideForMail(array $xactions) {
    switch ($this->getTransactionType()) {
      case DifferentialRevisionReviewersTransaction::TRANSACTIONTYPE:
        // Don't hide the initial "X added reviewers: ..." transaction during
        // object creation from mail. See T12118 and PHI54.
        return false;
    }

    return parent::shouldHideForMail($xactions);
  }


  public function isInlineCommentTransaction() {
    switch ($this->getTransactionType()) {
      case self::TYPE_INLINE:
        return true;
    }

    return parent::isInlineCommentTransaction();
  }

  public function getRequiredHandlePHIDs() {
    $phids = parent::getRequiredHandlePHIDs();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_ACTION:
        if ($new == DifferentialAction::ACTION_CLOSE &&
            $this->getMetadataValue('isCommitClose')) {
          $phids[] = $this->getMetadataValue('commitPHID');
          if ($this->getMetadataValue('committerPHID')) {
            $phids[] = $this->getMetadataValue('committerPHID');
          }
          if ($this->getMetadataValue('authorPHID')) {
            $phids[] = $this->getMetadataValue('authorPHID');
          }
        }
        break;
    }

    return $phids;
  }

  public function getActionStrength() {
    switch ($this->getTransactionType()) {
      case self::TYPE_ACTION:
        return 300;
    }

    return parent::getActionStrength();
  }


  public function getActionName() {
    switch ($this->getTransactionType()) {
      case self::TYPE_INLINE:
        return pht('Commented On');
      case self::TYPE_ACTION:
        $map = array(
          DifferentialAction::ACTION_ACCEPT => pht('Accepted'),
          DifferentialAction::ACTION_REJECT => pht('Requested Changes To'),
          DifferentialAction::ACTION_RETHINK => pht('Planned Changes To'),
          DifferentialAction::ACTION_ABANDON => pht('Abandoned'),
          DifferentialAction::ACTION_CLOSE => pht('Closed'),
          DifferentialAction::ACTION_REQUEST => pht('Requested A Review Of'),
          DifferentialAction::ACTION_RESIGN => pht('Resigned From'),
          DifferentialAction::ACTION_ADDREVIEWERS => pht('Added Reviewers'),
          DifferentialAction::ACTION_CLAIM => pht('Commandeered'),
          DifferentialAction::ACTION_REOPEN => pht('Reopened'),
        );
        $name = idx($map, $this->getNewValue());
        if ($name !== null) {
          return $name;
        }
        break;
    }

    return parent::getActionName();
  }

  public function getMailTags() {
    $tags = array();

    switch ($this->getTransactionType()) {
      case PhabricatorTransactions::TYPE_SUBSCRIBERS;
        $tags[] = self::MAILTAG_CC;
        break;
      case self::TYPE_ACTION:
        switch ($this->getNewValue()) {
          case DifferentialAction::ACTION_CLOSE:
            $tags[] = self::MAILTAG_CLOSED;
            break;
        }
        break;
      case DifferentialRevisionUpdateTransaction::TRANSACTIONTYPE:
        $old = $this->getOldValue();
        if ($old === null) {
          $tags[] = self::MAILTAG_REVIEW_REQUEST;
        } else {
          $tags[] = self::MAILTAG_UPDATED;
        }
        break;
      case PhabricatorTransactions::TYPE_COMMENT:
      case self::TYPE_INLINE:
        $tags[] = self::MAILTAG_COMMENT;
        break;
      case DifferentialRevisionReviewersTransaction::TRANSACTIONTYPE:
        $tags[] = self::MAILTAG_REVIEWERS;
        break;
      case DifferentialRevisionCloseTransaction::TRANSACTIONTYPE:
        $tags[] = self::MAILTAG_CLOSED;
        break;
    }

    if (!$tags) {
      $tags[] = self::MAILTAG_OTHER;
    }

    return $tags;
  }

  public function getTitle() {
    $author_phid = $this->getAuthorPHID();
    $author_handle = $this->renderHandleLink($author_phid);

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_INLINE:
        return pht(
          '%s added inline comments.',
          $author_handle);
      case self::TYPE_ACTION:
        switch ($new) {
          case DifferentialAction::ACTION_CLOSE:
            if (!$this->getMetadataValue('isCommitClose')) {
              return DifferentialAction::getBasicStoryText(
                $new,
                $author_handle);
            }
            $commit_name = $this->renderHandleLink(
              $this->getMetadataValue('commitPHID'));
            $committer_phid = $this->getMetadataValue('committerPHID');
            $author_phid = $this->getMetadataValue('authorPHID');
            if ($this->getHandleIfExists($committer_phid)) {
              $committer_name = $this->renderHandleLink($committer_phid);
            } else {
              $committer_name = $this->getMetadataValue('committerName');
            }
            if ($this->getHandleIfExists($author_phid)) {
              $author_name = $this->renderHandleLink($author_phid);
            } else {
              $author_name = $this->getMetadataValue('authorName');
            }

            if ($committer_name && ($committer_name != $author_name)) {
              return pht(
                'Closed by commit %s (authored by %s, committed by %s).',
                $commit_name,
                $author_name,
                $committer_name);
            } else {
              return pht(
                'Closed by commit %s (authored by %s).',
                $commit_name,
                $author_name);
            }
            break;
          default:
            return DifferentialAction::getBasicStoryText($new, $author_handle);
        }
        break;
     }

    return parent::getTitle();
  }

  public function renderExtraInformationLink() {
    if ($this->getMetadataValue('revisionMatchData')) {
      $details_href =
        '/differential/revision/closedetails/'.$this->getPHID().'/';
      $details_link = javelin_tag(
        'a',
        array(
          'href' => $details_href,
          'sigil' => 'workflow',
        ),
        pht('Explain Why'));
      return $details_link;
    }
    return parent::renderExtraInformationLink();
  }

  public function getTitleForFeed() {
    $author_phid = $this->getAuthorPHID();
    $object_phid = $this->getObjectPHID();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    $author_link = $this->renderHandleLink($author_phid);
    $object_link = $this->renderHandleLink($object_phid);

    switch ($this->getTransactionType()) {
      case self::TYPE_INLINE:
        return pht(
          '%s added inline comments to %s.',
          $author_link,
          $object_link);
      case self::TYPE_ACTION:
        switch ($new) {
          case DifferentialAction::ACTION_ACCEPT:
            return pht(
              '%s accepted %s.',
              $author_link,
              $object_link);
          case DifferentialAction::ACTION_REJECT:
            return pht(
              '%s requested changes to %s.',
              $author_link,
              $object_link);
          case DifferentialAction::ACTION_RETHINK:
            return pht(
              '%s planned changes to %s.',
              $author_link,
              $object_link);
          case DifferentialAction::ACTION_ABANDON:
            return pht(
              '%s abandoned %s.',
              $author_link,
              $object_link);
          case DifferentialAction::ACTION_CLOSE:
            if (!$this->getMetadataValue('isCommitClose')) {
              return pht(
                '%s closed %s.',
                $author_link,
                $object_link);
            } else {
              $commit_name = $this->renderHandleLink(
                $this->getMetadataValue('commitPHID'));
              $committer_phid = $this->getMetadataValue('committerPHID');
              $author_phid = $this->getMetadataValue('authorPHID');

              if ($this->getHandleIfExists($committer_phid)) {
                $committer_name = $this->renderHandleLink($committer_phid);
              } else {
                $committer_name = $this->getMetadataValue('committerName');
              }

              if ($this->getHandleIfExists($author_phid)) {
                $author_name = $this->renderHandleLink($author_phid);
              } else {
                $author_name = $this->getMetadataValue('authorName');
              }

              // Check if the committer and author are the same. They're the
              // same if both resolved and are the same user, or if neither
              // resolved and the text is identical.
              if ($committer_phid && $author_phid) {
                $same_author = ($committer_phid == $author_phid);
              } else if (!$committer_phid && !$author_phid) {
                $same_author = ($committer_name == $author_name);
              } else {
                $same_author = false;
              }

              if ($committer_name && !$same_author) {
                return pht(
                  '%s closed %s by committing %s (authored by %s).',
                  $author_link,
                  $object_link,
                  $commit_name,
                  $author_name);
              } else {
                return pht(
                  '%s closed %s by committing %s.',
                  $author_link,
                  $object_link,
                  $commit_name);
              }
            }
            break;

          case DifferentialAction::ACTION_REQUEST:
            return pht(
              '%s requested review of %s.',
              $author_link,
              $object_link);
          case DifferentialAction::ACTION_RECLAIM:
            return pht(
              '%s reclaimed %s.',
              $author_link,
              $object_link);
          case DifferentialAction::ACTION_RESIGN:
            return pht(
              '%s resigned from %s.',
              $author_link,
              $object_link);
          case DifferentialAction::ACTION_CLAIM:
            return pht(
              '%s commandeered %s.',
              $author_link,
              $object_link);
          case DifferentialAction::ACTION_REOPEN:
            return pht(
              '%s reopened %s.',
              $author_link,
              $object_link);
        }
        break;
     }

    return parent::getTitleForFeed();
  }

  public function getIcon() {
    switch ($this->getTransactionType()) {
      case self::TYPE_INLINE:
        return 'fa-comment';
      case self::TYPE_ACTION:
        switch ($this->getNewValue()) {
          case DifferentialAction::ACTION_CLOSE:
            return 'fa-check';
          case DifferentialAction::ACTION_ACCEPT:
            return 'fa-check-circle-o';
          case DifferentialAction::ACTION_REJECT:
            return 'fa-times-circle-o';
          case DifferentialAction::ACTION_ABANDON:
            return 'fa-plane';
          case DifferentialAction::ACTION_RETHINK:
            return 'fa-headphones';
          case DifferentialAction::ACTION_REQUEST:
            return 'fa-refresh';
          case DifferentialAction::ACTION_RECLAIM:
          case DifferentialAction::ACTION_REOPEN:
            return 'fa-bullhorn';
          case DifferentialAction::ACTION_RESIGN:
            return 'fa-flag';
          case DifferentialAction::ACTION_CLAIM:
            return 'fa-flag';
        }
      case PhabricatorTransactions::TYPE_EDGE:
        switch ($this->getMetadataValue('edge:type')) {
          case DifferentialRevisionHasReviewerEdgeType::EDGECONST:
            return 'fa-user';
        }
    }

    return parent::getIcon();
  }

  public function shouldDisplayGroupWith(array $group) {

    // Never group status changes with other types of actions, they're indirect
    // and don't make sense when combined with direct actions.

    if ($this->isStatusTransaction($this)) {
      return false;
    }

    foreach ($group as $xaction) {
      if ($this->isStatusTransaction($xaction)) {
        return false;
      }
    }

    return parent::shouldDisplayGroupWith($group);
  }

  private function isStatusTransaction($xaction) {
    $status_type = DifferentialRevisionStatusTransaction::TRANSACTIONTYPE;
    if ($xaction->getTransactionType() == $status_type) {
      return true;
    }

    return false;
  }


  public function getColor() {
    switch ($this->getTransactionType()) {
      case self::TYPE_ACTION:
        switch ($this->getNewValue()) {
          case DifferentialAction::ACTION_CLOSE:
            return PhabricatorTransactions::COLOR_INDIGO;
          case DifferentialAction::ACTION_ACCEPT:
            return PhabricatorTransactions::COLOR_GREEN;
          case DifferentialAction::ACTION_REJECT:
            return PhabricatorTransactions::COLOR_RED;
          case DifferentialAction::ACTION_ABANDON:
            return PhabricatorTransactions::COLOR_INDIGO;
          case DifferentialAction::ACTION_RETHINK:
            return PhabricatorTransactions::COLOR_RED;
          case DifferentialAction::ACTION_REQUEST:
            return PhabricatorTransactions::COLOR_SKY;
          case DifferentialAction::ACTION_RECLAIM:
            return PhabricatorTransactions::COLOR_SKY;
          case DifferentialAction::ACTION_REOPEN:
            return PhabricatorTransactions::COLOR_SKY;
          case DifferentialAction::ACTION_RESIGN:
            return PhabricatorTransactions::COLOR_ORANGE;
          case DifferentialAction::ACTION_CLAIM:
            return PhabricatorTransactions::COLOR_YELLOW;
        }
    }


    return parent::getColor();
  }

  public function getNoEffectDescription() {
    switch ($this->getTransactionType()) {
      case self::TYPE_ACTION:
        switch ($this->getNewValue()) {
          case DifferentialAction::ACTION_CLOSE:
            return pht('This revision is already closed.');
          case DifferentialAction::ACTION_ABANDON:
            return pht('This revision has already been abandoned.');
          case DifferentialAction::ACTION_RECLAIM:
            return pht(
              'You can not reclaim this revision because his revision is '.
              'not abandoned.');
          case DifferentialAction::ACTION_REOPEN:
            return pht(
              'You can not reopen this revision because this revision is '.
              'not closed.');
          case DifferentialAction::ACTION_RETHINK:
            return pht('This revision already requires changes.');
          case DifferentialAction::ACTION_CLAIM:
            return pht(
              'You can not commandeer this revision because you already own '.
              'it.');
        }
        break;
    }

    return parent::getNoEffectDescription();
  }

  public function renderAsTextForDoorkeeper(
    DoorkeeperFeedStoryPublisher $publisher,
    PhabricatorFeedStory $story,
    array $xactions) {

    $body = parent::renderAsTextForDoorkeeper($publisher, $story, $xactions);

    $inlines = array();
    foreach ($xactions as $xaction) {
      if ($xaction->getTransactionType() == self::TYPE_INLINE) {
        $inlines[] = $xaction;
      }
    }

    // TODO: This is a bit gross, but far less bad than it used to be. It
    // could be further cleaned up at some point.

    if ($inlines) {
      $engine = PhabricatorMarkupEngine::newMarkupEngine(array())
        ->setConfig('viewer', new PhabricatorUser())
        ->setMode(PhutilRemarkupEngine::MODE_TEXT);

      $body .= "\n\n";
      $body .= pht('Inline Comments');
      $body .= "\n";

      $changeset_ids = array();
      foreach ($inlines as $inline) {
        $changeset_ids[] = $inline->getComment()->getChangesetID();
      }

      $changesets = id(new DifferentialChangeset())->loadAllWhere(
        'id IN (%Ld)',
        $changeset_ids);

      foreach ($inlines as $inline) {
        $comment = $inline->getComment();
        $changeset = idx($changesets, $comment->getChangesetID());
        if (!$changeset) {
          continue;
        }

        $filename = $changeset->getDisplayFilename();
        $linenumber = $comment->getLineNumber();
        $inline_text = $engine->markupText($comment->getContent());
        $inline_text = rtrim($inline_text);

        $body .= "{$filename}:{$linenumber} {$inline_text}\n";
      }
    }

    return $body;
  }

  public function newWarningForTransactions($object, array $xactions) {
    $warning = new PhabricatorTransactionWarning();

    switch ($this->getTransactionType()) {
      case self::TYPE_INLINE:
        $warning->setTitleText(pht('Warning: Editing Inlines'));
        $warning->setContinueActionText(pht('Save Inlines and Continue'));

        $count = phutil_count($xactions);

        $body = array();
        $body[] = pht(
          'You are currently editing %s inline comment(s) on this '.
          'revision.',
          $count);
        $body[] = pht(
          'These %s inline comment(s) will be saved and published.',
          $count);

        $warning->setWarningParagraphs($body);
        break;
      case PhabricatorTransactions::TYPE_SUBSCRIBERS:
        $warning->setTitleText(pht('Warning: Draft Revision'));
        $warning->setContinueActionText(pht('Tell No One'));

        $body = array();

        $body[] = pht(
          'This is a draft revision that will not publish any '.
          'notifications until the author requests review.');

        $body[] = pht('Mentioned or subscribed users will not be notified.');

        $warning->setWarningParagraphs($body);
        break;
    }

    return $warning;
  }


}
