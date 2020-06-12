<?php

final class DiffusionDiffInlineCommentQuery
  extends PhabricatorDiffInlineCommentQuery {

  private $commitPHIDs;
  private $pathIDs;

  protected function newApplicationTransactionCommentTemplate() {
    return new PhabricatorAuditTransactionComment();
  }

  public function withCommitPHIDs(array $phids) {
    $this->commitPHIDs = $phids;
    return $this;
  }

  public function withObjectPHIDs(array $phids) {
    return $this->withCommitPHIDs($phids);
  }

  public function withPathIDs(array $path_ids) {
    $this->pathIDs = $path_ids;
    return $this;
  }

  protected function buildInlineCommentWhereClauseParts(
    AphrontDatabaseConnection $conn) {
    $where = array();
    $alias = $this->getPrimaryTableAlias();

    $where[] = qsprintf(
      $conn,
      '%T.pathID IS NOT NULL',
      $alias);

    return $where;
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);
    $alias = $this->getPrimaryTableAlias();

    if ($this->commitPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        '%T.commitPHID IN (%Ls)',
        $alias,
        $this->commitPHIDs);
    }

    if ($this->pathIDs !== null) {
      $where[] = qsprintf(
        $conn,
        '%T.pathID IN (%Ld)',
        $alias,
        $this->pathIDs);
    }

    return $where;
  }

  protected function loadHiddenCommentIDs(
    $viewer_phid,
    array $comments) {
    return array();
  }

  protected function newInlineContextMap(array $inlines) {
    return array();
  }

  protected function newInlineContextFromCacheData(array $map) {
    return PhabricatorDiffInlineCommentContext::newFromCacheData($map);
  }

}
