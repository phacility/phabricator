<?php

abstract class PhabricatorDiffInlineCommentQuery
  extends PhabricatorApplicationTransactionCommentQuery {

  private $fixedStates;
  private $needReplyToComments;
  private $publishedComments;
  private $publishableComments;
  private $needHidden;

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

  protected function willFilterPage(array $comments) {
    if ($this->needReplyToComments) {
      $reply_phids = array();
      foreach ($comments as $comment) {
        $reply_phid = $comment->getReplyToCommentPHID();
        if ($reply_phid) {
          $reply_phids[] = $reply_phid;
        }
      }

      if ($reply_phids) {
        $reply_comments = newv(get_class($this), array())
          ->setViewer($this->getViewer())
          ->setParentQuery($this)
          ->withPHIDs($reply_phids)
          ->execute();
        $reply_comments = mpull($reply_comments, null, 'getPHID');
      } else {
        $reply_comments = array();
      }

      foreach ($comments as $key => $comment) {
        $reply_phid = $comment->getReplyToCommentPHID();
        if (!$reply_phid) {
          $comment->attachReplyToComment(null);
          continue;
        }
        $reply = idx($reply_comments, $reply_phid);
        if (!$reply) {
          $this->didRejectResult($comment);
          unset($comments[$key]);
          continue;
        }
        $comment->attachReplyToComment($reply);
      }
    }

    if (!$comments) {
      return $comments;
    }

    if ($this->needHidden) {
      $viewer = $this->getViewer();
      $viewer_phid = $viewer->getPHID();

      if ($viewer_phid) {
        $hidden = $this->loadHiddenCommentIDs(
          $viewer_phid,
          $comments);
      } else {
        $hidden = array();
      }

      foreach ($comments as $inline) {
        $inline->attachIsHidden(isset($hidden[$inline->getID()]));
      }
    }

    return $comments;
  }

}
