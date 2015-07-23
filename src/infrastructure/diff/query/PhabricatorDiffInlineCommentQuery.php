<?php

abstract class PhabricatorDiffInlineCommentQuery
  extends PhabricatorApplicationTransactionCommentQuery {

  private $fixedStates;
  private $needReplyToComments;

  public function withFixedStates(array $states) {
    $this->fixedStates = $states;
    return $this;
  }

  public function needReplyToComments($need_reply_to) {
    $this->needReplyToComments = $need_reply_to;
    return $this;
  }

  protected function buildWhereClauseComponents(
    AphrontDatabaseConnection $conn_r) {
    $where = parent::buildWhereClauseComponents($conn_r);

    if ($this->fixedStates !== null) {
      $where[] = qsprintf(
        $conn_r,
        'fixedState IN (%Ls)',
        $this->fixedStates);
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

    return $comments;
  }

}
