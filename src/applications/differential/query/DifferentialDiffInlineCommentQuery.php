<?php

final class DifferentialDiffInlineCommentQuery
  extends PhabricatorDiffInlineCommentQuery {

  private $revisionPHIDs;

  protected function newApplicationTransactionCommentTemplate() {
    return new DifferentialTransactionComment();
  }

  public function withRevisionPHIDs(array $phids) {
    $this->revisionPHIDs = $phids;
    return $this;
  }

  public function withObjectPHIDs(array $phids) {
    return $this->withRevisionPHIDs($phids);
  }

  protected function buildInlineCommentWhereClauseParts(
    AphrontDatabaseConnection $conn) {
    $where = array();
    $alias = $this->getPrimaryTableAlias();

    $where[] = qsprintf(
      $conn,
      'changesetID IS NOT NULL');

    return $where;
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);
    $alias = $this->getPrimaryTableAlias();

    if ($this->revisionPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        '%T.revisionPHID IN (%Ls)',
        $alias,
        $this->revisionPHIDs);
    }

    return $where;
  }

  protected function loadHiddenCommentIDs(
    $viewer_phid,
    array $comments) {

    $table = new DifferentialHiddenComment();
    $conn = $table->establishConnection('r');

    $rows = queryfx_all(
      $conn,
      'SELECT commentID FROM %R
        WHERE userPHID = %s
        AND commentID IN (%Ld)',
      $table,
      $viewer_phid,
      mpull($comments, 'getID'));

    $id_map = ipull($rows, 'commentID');
    $id_map = array_fuse($id_map);

    return $id_map;
  }

}
