<?php

final class DifferentialCommentMail extends DifferentialMail {

  protected $changedByCommit;

  private $addedReviewers;
  private $addedCCs;

  public function setChangedByCommit($changed_by_commit) {
    $this->changedByCommit = $changed_by_commit;
    return $this;
  }

  public function getChangedByCommit() {
    return $this->changedByCommit;
  }

  public function __construct(
    DifferentialRevision $revision,
    PhabricatorObjectHandle $actor,
    DifferentialComment $comment,
    array $changesets,
    array $inline_comments) {
    assert_instances_of($changesets, 'DifferentialChangeset');
    assert_instances_of($inline_comments, 'PhabricatorInlineCommentInterface');

    $this->setRevision($revision);
    $this->setActorHandle($actor);
    $this->setComment($comment);
    $this->setChangesets($changesets);
    $this->setInlineComments($inline_comments);

  }

  protected function getMailTags() {
    $tags    = array();
    $comment = $this->getComment();
    $action  = $comment->getAction();

    switch ($action) {
      case DifferentialAction::ACTION_ADDCCS:
        $tags[] = MetaMTANotificationType::TYPE_DIFFERENTIAL_CC;
        break;
      case DifferentialAction::ACTION_CLOSE:
        $tags[] = MetaMTANotificationType::TYPE_DIFFERENTIAL_CLOSED;
        break;
      case DifferentialAction::ACTION_ADDREVIEWERS:
        $tags[] = MetaMTANotificationType::TYPE_DIFFERENTIAL_REVIEWERS;
        break;
      case DifferentialAction::ACTION_COMMENT:
        // this is a comment which we will check separately below for content
        break;
      default:
        $tags[] = MetaMTANotificationType::TYPE_DIFFERENTIAL_OTHER;
        break;
    }

    if (strlen(trim($comment->getContent()))) {
      switch ($action) {
        case DifferentialAction::ACTION_CLOSE:
          // Commit comments are auto-generated and not especially interesting,
          // so don't tag them as having a comment.
          break;
        default:
          $tags[] = MetaMTANotificationType::TYPE_DIFFERENTIAL_COMMENT;
          break;
      }
    }

    return $tags;
  }

  protected function renderVaryPrefix() {
    $verb = ucwords($this->getVerb());
    return "[{$verb}]";
  }

  protected function getVerb() {
    $comment = $this->getComment();
    $action = $comment->getAction();
    $verb = DifferentialAction::getActionPastTenseVerb($action);
    return $verb;
  }

  protected function prepareBody() {
    parent::prepareBody();

    // If the commented added reviewers or CCs, list them explicitly.
    $meta = $this->getComment()->getMetadata();
    $m_reviewers = idx(
      $meta,
      DifferentialComment::METADATA_ADDED_REVIEWERS,
      array());
    $m_cc = idx(
      $meta,
      DifferentialComment::METADATA_ADDED_CCS,
      array());
    $load = array_merge($m_reviewers, $m_cc);
    if ($load) {
      $handles = id(new PhabricatorHandleQuery())
        ->setViewer($this->getActor())
        ->withPHIDs($load)
        ->execute();
      if ($m_reviewers) {
        $this->addedReviewers = $this->renderHandleList($handles, $m_reviewers);
      }
      if ($m_cc) {
        $this->addedCCs = $this->renderHandleList($handles, $m_cc);
      }
    }
  }

  protected function indentForMail($lines) {
    foreach ($lines as &$line) {
      $line = "> " . $line;
    }
    return $lines;
  }

  protected function nestCommentHistory($inline) {
    $nested = array();
    $previous_inlines = id(new DifferentialTransactionComment())->loadAllWhere(
      "changesetID = %d AND lineNumber = %d AND id < %d ".
      "ORDER BY id ASC",
      $inline->getChangesetID(), $inline->getLineNumber(), $inline->getID());
    foreach ($previous_inlines as $previous_inline) {
      $nested = $this->indentForMail(
        array_merge(
          $nested,
          explode("\n", $previous_inline->getContent())));
      $user = id(new PhabricatorUser())->loadOneWhere(
        "phid = %s", $previous_inline->getAuthorPHID());
      if ($user) {
        array_unshift($nested, $user->getRealName() . " wrote:");
      }
    }
    $nested = array_merge($nested, explode("\n", $inline->getContent()));
    return implode("\n", $nested);
  }

  protected function renderBody() {

    $comment = $this->getComment();

    $actor = $this->getActorName();
    $name  = $this->getRevision()->getTitle();
    $verb  = $this->getVerb();

    $body  = array();

    $body[] = "{$actor} has {$verb} the revision \"{$name}\".";

    if ($this->addedReviewers) {
      $body[] = 'Added Reviewers: '.$this->addedReviewers;
    }
    if ($this->addedCCs) {
      $body[] = 'Added CCs: '.$this->addedCCs;
    }

    $body[] = null;

    $content = $comment->getContent();
    if (strlen($content)) {
      $body[] = $this->formatText($content);
      $body[] = null;
    }

    if ($this->getChangedByCommit()) {
      $body[] = 'CHANGED PRIOR TO COMMIT';
      $body[] = '  '.$this->getChangedByCommit();
      $body[] = null;
    }

    $inlines = $this->getInlineComments();
    if ($inlines) {
      $body[] = 'INLINE COMMENTS';
      $changesets = $this->getChangesets();
      $hunk_parser = new DifferentialHunkParser();

      if (PhabricatorEnv::getEnvConfig(
            'metamta.differential.unified-comment-context')) {
        foreach ($changesets as $changeset) {
          $changeset->attachHunks($changeset->loadHunks());
        }
      }
      foreach ($inlines as $inline) {
        $changeset = $changesets[$inline->getChangesetID()];
        if (!$changeset) {
          throw new Exception('Changeset missing!');
        }
        $file = $changeset->getFilename();
        $start = $inline->getLineNumber();
        $len = $inline->getLineLength();
        if ($len) {
          $range = $start.'-'.($start + $len);
        } else {
          $range = $start;
        }

        $inline_content = $inline->getContent();

        if (!PhabricatorEnv::getEnvConfig(
              'metamta.differential.unified-comment-context')) {
          $body[] = $this->formatText("{$file}:{$range} {$inline_content}");
        } else {
          $body[] = "================";
          $body[] = "Comment at: " . $file . ":" . $range;
          $body[] = $hunk_parser->makeContextDiff(
            $changeset->getHunks(),
            $inline,
            1);
          $body[] = "----------------";

          if (PhabricatorEnv::getEnvConfig(
                'differential.email-comment-context', false)) {
            $body[] = $this->nestCommentHistory($inline);
          } else {
            $body[] = $inline_content;
          }
          $body[] = null;
        }
      }
      $body[] = null;
    }

    $body[] = $this->renderAuxFields(DifferentialMailPhase::COMMENT);

    return implode("\n", $body);
  }
}
