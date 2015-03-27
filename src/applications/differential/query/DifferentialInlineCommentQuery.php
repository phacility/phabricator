<?php

/**
 * Temporary wrapper for transitioning Differential to ApplicationTransactions.
 */
final class DifferentialInlineCommentQuery
  extends PhabricatorOffsetPagedQuery {

  private $revisionIDs;
  private $notDraft;
  private $ids;
  private $phids;
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

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
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

  public function execute() {
    $table = new DifferentialTransactionComment();
    $conn_r = $table->establishConnection('r');

    $data = queryfx_all(
      $conn_r,
      'SELECT * FROM %T %Q %Q',
      $table->getTableName(),
      $this->buildWhereClause($conn_r),
      $this->buildLimitClause($conn_r));

    $comments = $table->loadAllFromArray($data);

    foreach ($comments as $key => $value) {
      $comments[$key] = DifferentialInlineComment::newFromModernComment(
        $value);
    }

    return $comments;
  }

  public function executeOne() {
    return head($this->execute());
  }

  private function buildWhereClause(AphrontDatabaseConnection $conn_r) {
    $where = array();

    // Only find inline comments.
    $where[] = qsprintf(
      $conn_r,
      'changesetID IS NOT NULL');

    if ($this->revisionIDs) {

      // Look up revision PHIDs.
      $revision_phids = queryfx_all(
        $conn_r,
        'SELECT phid FROM %T WHERE id IN (%Ld)',
        id(new DifferentialRevision())->getTableName(),
        $this->revisionIDs);

      if (!$revision_phids) {
        throw new PhabricatorEmptyQueryException();
      }
      $revision_phids = ipull($revision_phids, 'phid');

      $where[] = qsprintf(
        $conn_r,
        'revisionPHID IN (%Ls)',
        $revision_phids);
    }

    if ($this->notDraft) {
      $where[] = qsprintf(
        $conn_r,
        'transactionPHID IS NOT NULL');
    }

    if ($this->ids) {
      $where[] = qsprintf(
        $conn_r,
        'id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn_r,
        'phid IN (%Ls)',
        $this->phids);
    }

    if ($this->viewerAndChangesetIDs) {
      list($phid, $ids) = $this->viewerAndChangesetIDs;
      $where[] = qsprintf(
        $conn_r,
        'changesetID IN (%Ld) AND
          ((authorPHID = %s AND isDeleted = 0) OR transactionPHID IS NOT NULL)',
        $ids,
        $phid);
    }

    if ($this->draftComments) {
      list($phid, $rev_id) = $this->draftComments;

      $rev_phid = queryfx_one(
        $conn_r,
        'SELECT phid FROM %T WHERE id = %d',
        id(new DifferentialRevision())->getTableName(),
        $rev_id);

      if (!$rev_phid) {
        throw new PhabricatorEmptyQueryException();
      }

      $rev_phid = $rev_phid['phid'];

      $where[] = qsprintf(
        $conn_r,
        'authorPHID = %s AND revisionPHID = %s AND transactionPHID IS NULL
          AND isDeleted = 0',
        $phid,
        $rev_phid);
    }

    if ($this->draftsByAuthors) {
      $where[] = qsprintf(
        $conn_r,
        'authorPHID IN (%Ls) AND isDeleted = 0 AND transactionPHID IS NULL',
        $this->draftsByAuthors);
    }

    return $this->formatWhereClause($where);
  }

}
