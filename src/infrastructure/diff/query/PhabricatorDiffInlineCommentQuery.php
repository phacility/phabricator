<?php

abstract class PhabricatorDiffInlineCommentQuery
  extends PhabricatorApplicationTransactionCommentQuery {

  private $fixedStates;
  private $needReplyToComments;
  private $publishedComments;
  private $publishableComments;
  private $needHidden;
  private $needAppliedDrafts;

  abstract protected function buildInlineCommentWhereClauseParts(
    AphrontDatabaseConnection $conn);
  abstract public function withObjectPHIDs(array $phids);
  abstract protected function loadHiddenCommentIDs(
    $viewer_phid,
    array $comments);

  final public function withFixedStates(array $states) {
    $this->fixedStates = $states;
    return $this;
  }

  final public function needReplyToComments($need_reply_to) {
    $this->needReplyToComments = $need_reply_to;
    return $this;
  }

  final public function withPublishableComments($with_publishable) {
    $this->publishableComments = $with_publishable;
    return $this;
  }

  final public function withPublishedComments($with_published) {
    $this->publishedComments = $with_published;
    return $this;
  }

  final public function needHidden($need_hidden) {
    $this->needHidden = $need_hidden;
    return $this;
  }

  final public function needAppliedDrafts($need_applied) {
    $this->needAppliedDrafts = $need_applied;
    return $this;
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);
    $alias = $this->getPrimaryTableAlias();

    foreach ($this->buildInlineCommentWhereClauseParts($conn) as $part) {
      $where[] = $part;
    }

    if ($this->fixedStates !== null) {
      $where[] = qsprintf(
        $conn,
        '%T.fixedState IN (%Ls)',
        $alias,
        $this->fixedStates);
    }

    $show_published = false;
    $show_publishable = false;

    if ($this->publishableComments !== null) {
      if (!$this->publishableComments) {
        throw new Exception(
          pht(
            'Querying for comments that are "not publishable" is '.
            'not supported.'));
      }
      $show_publishable = true;
    }

    if ($this->publishedComments !== null) {
      if (!$this->publishedComments) {
        throw new Exception(
          pht(
            'Querying for comments that are "not published" is '.
            'not supported.'));
      }
      $show_published = true;
    }

    if ($show_publishable || $show_published) {
      $clauses = array();

      if ($show_published) {
        $clauses[] = qsprintf(
          $conn,
          '%T.transactionPHID IS NOT NULL',
          $alias);
      }

      if ($show_publishable) {
        $viewer = $this->getViewer();
        $viewer_phid = $viewer->getPHID();

        // If the viewer has a PHID, unpublished comments they authored and
        // have not deleted are visible.
        if ($viewer_phid) {
          $clauses[] = qsprintf(
            $conn,
            '%T.authorPHID = %s
              AND %T.isDeleted = 0
              AND %T.transactionPHID IS NULL ',
            $alias,
            $viewer_phid,
            $alias,
            $alias);
        }
      }

      // We can end up with a known-empty query if we (for example) query for
      // publishable comments and the viewer is logged-out.
      if (!$clauses) {
        throw new PhabricatorEmptyQueryException();
      }

      $where[] = qsprintf(
        $conn,
        '%LO',
        $clauses);
    }

    return $where;
  }

  protected function willFilterPage(array $inlines) {
    $viewer = $this->getViewer();

    if ($this->needReplyToComments) {
      $reply_phids = array();
      foreach ($inlines as $inline) {
        $reply_phid = $inline->getReplyToCommentPHID();
        if ($reply_phid) {
          $reply_phids[] = $reply_phid;
        }
      }

      if ($reply_phids) {
        $reply_inlines = newv(get_class($this), array())
          ->setViewer($this->getViewer())
          ->setParentQuery($this)
          ->withPHIDs($reply_phids)
          ->execute();
        $reply_inlines = mpull($reply_inlines, null, 'getPHID');
      } else {
        $reply_inlines = array();
      }

      foreach ($inlines as $key => $inline) {
        $reply_phid = $inline->getReplyToCommentPHID();
        if (!$reply_phid) {
          $inline->attachReplyToComment(null);
          continue;
        }
        $reply = idx($reply_inlines, $reply_phid);
        if (!$reply) {
          $this->didRejectResult($inline);
          unset($inlines[$key]);
          continue;
        }
        $inline->attachReplyToComment($reply);
      }
    }

    if (!$inlines) {
      return $inlines;
    }

    if ($this->needHidden) {
      $viewer_phid = $viewer->getPHID();

      if ($viewer_phid) {
        $hidden = $this->loadHiddenCommentIDs(
          $viewer_phid,
          $inlines);
      } else {
        $hidden = array();
      }

      foreach ($inlines as $inline) {
        $inline->attachIsHidden(isset($hidden[$inline->getID()]));
      }
    }

    if (!$inlines) {
      return $inlines;
    }

    $need_drafts = $this->needAppliedDrafts;
    $drop_void = $this->publishableComments;
    $convert_objects = ($need_drafts || $drop_void);

    if ($convert_objects) {
      $inlines = mpull($inlines, 'newInlineCommentObject');

      PhabricatorInlineComment::loadAndAttachVersionedDrafts(
        $viewer,
        $inlines);

      if ($need_drafts) {
        // Don't count void inlines when considering draft state.
        foreach ($inlines as $key => $inline) {
          if ($inline->isVoidComment($viewer)) {
            $this->didRejectResult($inline->getStorageObject());
            unset($inlines[$key]);
            continue;
          }

          // For other inlines: if they have a nonempty draft state, set their
          // content to the draft state content. We want to submit the comment
          // as it is currently shown to the user, not as it was stored the last
          // time they clicked "Save".

          $draft_content = $inline->getContentForEdit($viewer);
          if (strlen($draft_content)) {
            $inline->setContent($draft_content);
          }
        }
      }

      // If we're loading publishable comments, discard any comments that are
      // empty.
      if ($drop_void) {
        foreach ($inlines as $key => $inline) {
          if ($inline->getTransactionPHID()) {
            continue;
          }

          if ($inline->isVoidComment($viewer)) {
            $this->didRejectResult($inline->getStorageObject());
            unset($inlines[$key]);
            continue;
          }
        }
      }

      $inlines = mpull($inlines, 'getStorageObject');
    }

    return $inlines;
  }

}
