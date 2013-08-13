<?php

/**
 * Temporary wrapper for transitioning Differential to ApplicationTransactions.
 */
final class DifferentialInlineCommentQuery
  extends PhabricatorOffsetPagedQuery {

  private $revisionIDs;
  private $notDraft;
  private $ids;
  private $commentIDs;

  private $viewerAndChangesetIDs;
  private $draftComments;
  private $draftsByAuthors;

  public function withRevisionIDs(array $ids) {
    $this->revisionIDs = $ids;
    return $this;
  }

  public function withNotDraft($not_draft) {
    $this->notDraft = $not_draft;
    return $this;
  }

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withViewerAndChangesetIDs($author_phid, array $ids) {
    $this->viewerAndChangesetIDs = array($author_phid, $ids);
    return $this;
  }

  public function withDraftComments($author_phid, $revision_id) {
    $this->draftComments = array($author_phid, $revision_id);
    return $this;
  }

  public function withDraftsByAuthors(array $author_phids) {
    $this->draftsByAuthors = $author_phids;
    return $this;
  }

  public function withCommentIDs(array $comment_ids) {
    $this->commentIDs = $comment_ids;
    return $this;
  }

  public function execute() {
    $table = new DifferentialInlineComment();
    $conn_r = $table->establishConnection('r');

    $data = queryfx_all(
      $conn_r,
      'SELECT * FROM %T %Q %Q',
      $table->getTableName(),
      $this->buildWhereClause($conn_r),
      $this->buildLimitClause($conn_r));

    return $table->loadAllFromArray($data);
  }

  public function executeOne() {
    return head($this->execute());
  }

  private function buildWhereClause(AphrontDatabaseConnection $conn_r) {
    $where = array();

    if ($this->revisionIDs) {
      $where[] = qsprintf(
        $conn_r,
        'revisionID IN (%Ld)',
        $this->revisionIDs);
    }

    if ($this->notDraft) {
      $where[] = qsprintf(
        $conn_r,
        'commentID IS NOT NULL');
    }

    if ($this->ids) {
      $where[] = qsprintf(
        $conn_r,
        'id IN (%Ld)',
        $this->ids);
    }

    if ($this->viewerAndChangesetIDs) {
      list($phid, $ids) = $this->viewerAndChangesetIDs;
      $where[] = qsprintf(
        $conn_r,
        'changesetID IN (%Ld) AND (authorPHID = %s OR commentID IS NOT NULL)',
        $ids,
        $phid);
    }

    if ($this->draftComments) {
      list($phid, $rev_id) = $this->draftComments;
      $where[] = qsprintf(
        $conn_r,
        'authorPHID = %s AND revisionID = %d AND commentID IS NULL',
        $phid,
        $rev_id);
    }

    if ($this->draftsByAuthors) {
      $where[] = qsprintf(
        $conn_r,
        'authorPHID IN (%Ls) AND commentID IS NULL',
        $this->draftsByAuthors);
    }

    if ($this->commentIDs) {
      $where[] = qsprintf(
        $conn_r,
        'commentID IN (%Ld)',
        $this->commentIDs);
    }

    return $this->formatWhereClause($where);
  }

}
