<?php

/**
 * Temporary wrapper for transitioning Differential to ApplicationTransactions.
 */
final class DifferentialCommentQuery
  extends PhabricatorOffsetPagedQuery {

  private $revisionIDs;

  public function withRevisionIDs(array $ids) {
    $this->revisionIDs = $ids;
    return $this;
  }

  public function execute() {
    $table = new DifferentialComment();
    $conn_r = $table->establishConnection('r');

    $data = queryfx_all(
      $conn_r,
      'SELECT * FROM %T %Q %Q',
      $table->getTableName(),
      $this->buildWhereClause($conn_r),
      $this->buildLimitClause($conn_r));

    $comments = $table->loadAllFromArray($data);

    // We've moved the actual text storage into DifferentialTransactionComment,
    // so load the relevant pieces of text we need.
    if ($comments) {
      $this->loadCommentText($comments);
    }

    return $comments;
  }

  private function buildWhereClause(AphrontDatabaseConnection $conn_r) {
    $where = array();

    if ($this->revisionIDs) {
      $where[] = qsprintf(
        $conn_r,
        'revisionID IN (%Ld)',
        $this->revisionIDs);
    }

    return $this->formatWhereClause($where);
  }

  private function loadCommentText(array $comments) {
    $table = new DifferentialTransactionComment();
    $conn_r = $table->establishConnection('r');

    $data = queryfx_all(
      $conn_r,
      'SELECT * FROM %T WHERE legacyCommentID IN (%Ld) AND changesetID IS NULL',
      $table->getTableName(),
      mpull($comments, 'getID'));
    $texts = $table->loadAllFromArray($data);
    $texts = mpull($texts, null, 'getLegacyCommentID');

    foreach ($comments as $comment) {
      $text = idx($texts, $comment->getID());
      if ($text) {
        $comment->setProxyComment($text);
      }
    }
  }


}
