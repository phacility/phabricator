<?php

/**
 * Temporary wrapper for transitioning Differential to ApplicationTransactions.
 */
final class DifferentialInlineCommentQuery
  extends PhabricatorOffsetPagedQuery {

  // TODO: Remove this when this query eventually moves to PolicyAware.
  private $viewer;

  private $ids;
  private $phids;
  private $drafts;
  private $authorPHIDs;
  private $revisionPHIDs;
  private $deletedDrafts;

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function getViewer() {
    return $this->viewer;
  }

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withDrafts($drafts) {
    $this->drafts = $drafts;
    return $this;
  }

  public function withAuthorPHIDs(array $author_phids) {
    $this->authorPHIDs = $author_phids;
    return $this;
  }

  public function withRevisionPHIDs(array $revision_phids) {
    $this->revisionPHIDs = $revision_phids;
    return $this;
  }

  public function withDeletedDrafts($deleted_drafts) {
    $this->deletedDrafts = $deleted_drafts;
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
    // TODO: Remove when this query moves to PolicyAware.
    return head($this->execute());
  }

  protected function buildWhereClause(AphrontDatabaseConnection $conn_r) {
    $where = array();

    // Only find inline comments.
    $where[] = qsprintf(
      $conn_r,
      'changesetID IS NOT NULL');

    if ($this->ids !== null) {
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

    if ($this->revisionPHIDs !== null) {
      $where[] = qsprintf(
        $conn_r,
        'revisionPHID IN (%Ls)',
        $this->revisionPHIDs);
    }

    if ($this->drafts === null) {
      if ($this->deletedDrafts) {
        $where[] = qsprintf(
          $conn_r,
          '(authorPHID = %s) OR (transactionPHID IS NOT NULL)',
          $this->getViewer()->getPHID());
      } else {
        $where[] = qsprintf(
          $conn_r,
          '(authorPHID = %s AND isDeleted = 0)
            OR (transactionPHID IS NOT NULL)',
          $this->getViewer()->getPHID());
      }
    } else if ($this->drafts) {
      $where[] = qsprintf(
        $conn_r,
        '(authorPHID = %s AND isDeleted = 0) AND (transactionPHID IS NULL)',
        $this->getViewer()->getPHID());
    } else {
      $where[] = qsprintf(
        $conn_r,
        'transactionPHID IS NOT NULL');
    }

    return $this->formatWhereClause($where);
  }

}
