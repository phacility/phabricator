<?php

final class PonderCommentQuery extends PhabricatorQuery {

  private $ids;
  private $authorPHID;
  private $targetPHIDs;

  public function withIDs($qids) {
    $this->ids = $qids;
    return $this;
  }

  public function withTargetPHIDs($phids) {
    $this->targetPHIDs = $phids;
    return $this;
  }

  public function withAuthorPHID($phid) {
    $this->authorPHID = $phid;
    return $this;
  }

  private function buildWhereClause($conn_r) {
    $where = array();
    if ($this->ids) {
      $where[] = qsprintf($conn_r, 'id in (%Ls)', $this->ids);
    }
    if ($this->authorPHID) {
      $where[] = qsprintf($conn_r, 'authorPHID = %s', $this->authorPHID);
    }
    if ($this->targetPHIDs) {
      $where[] = qsprintf($conn_r, 'targetPHID in (%Ls)', $this->targetPHIDs);
    }

    return $this->formatWhereClause($where);
  }

  private function buildOrderByClause($conn_r) {
    return 'ORDER BY id';
  }

  public function execute() {
    $comment = new PonderComment();
    $conn_r = $comment->establishConnection('r');

    $select = qsprintf(
      $conn_r,
      'SELECT r.* FROM %T r',
      $comment->getTableName());

    $where = $this->buildWhereClause($conn_r);
    $order_by = $this->buildOrderByClause($conn_r);

    return $comment->loadAllFromArray(
      queryfx_all(
        $conn_r,
        '%Q %Q %Q',
        $select,
        $where,
        $order_by));
  }


}
