<?php

/**
 * @group slowvote
 */
final class PhabricatorSlowvoteQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $authorPHIDs;
  private $withVotesByViewer;

  public function withIDs($ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs($phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withAuthorPHIDs($author_phids) {
    $this->authorPHIDs = $author_phids;
    return $this;
  }

  public function withVotesByViewer($with_vote) {
    $this->withVotesByViewer = $with_vote;
    return $this;
  }

  public function loadPage() {
    $table = new PhabricatorSlowvotePoll();
    $conn_r = $table->establishConnection('r');

    $data = queryfx_all(
      $conn_r,
      'SELECT p.* FROM %T p %Q %Q %Q %Q',
      $table->getTableName(),
      $this->buildJoinsClause($conn_r),
      $this->buildWhereClause($conn_r),
      $this->buildOrderClause($conn_r),
      $this->buildLimitClause($conn_r));

    return $table->loadAllFromArray($data);
  }

  private function buildWhereClause(AphrontDatabaseConnection $conn_r) {
    $where = array();

    if ($this->ids) {
      $where[] = qsprintf(
        $conn_r,
        'p.id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids) {
      $where[] = qsprintf(
        $conn_r,
        'p.phid IN (%Ls)',
        $this->phids);
    }

    if ($this->authorPHIDs) {
      $where[] = qsprintf(
        $conn_r,
        'p.authorPHID IN (%Ls)',
        $this->authorPHIDs);
    }

    $where[] = $this->buildPagingClause($conn_r);
    return $this->formatWhereClause($where);
  }

  private function buildJoinsClause(AphrontDatabaseConnection $conn_r) {
    $joins = array();

    if ($this->withVotesByViewer) {
      $joins[] = qsprintf(
        $conn_r,
        'JOIN %T vv ON vv.pollID = p.id AND vv.authorPHID = %s',
        id(new PhabricatorSlowvoteChoice())->getTableName(),
        $this->getViewer()->getPHID());
    }

    return implode(' ', $joins);
  }

  protected function getPagingColumn() {
    return 'p.id';
  }

}
