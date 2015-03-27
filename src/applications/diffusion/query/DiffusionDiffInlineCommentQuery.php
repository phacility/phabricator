<?php

final class DiffusionDiffInlineCommentQuery
  extends PhabricatorDiffInlineCommentQuery {

  private $commitPHIDs;
  private $hasPath;
  private $pathIDs;

  public function withCommitPHIDs(array $phids) {
    $this->commitPHIDs = $phids;
    return $this;
  }

  public function withHasPath($has_path) {
    $this->hasPath = $has_path;
    return $this;
  }

  public function withPathIDs(array $path_ids) {
    $this->pathIDs = $path_ids;
    return $this;
  }

  protected function getTemplate() {
    return new PhabricatorAuditTransactionComment();
  }

  protected function buildWhereClauseComponents(
    AphrontDatabaseConnection $conn_r) {
    $where = parent::buildWhereClauseComponents($conn_r);

    if ($this->commitPHIDs !== null) {
      $where[] = qsprintf(
        $conn_r,
        'xcomment.commitPHID IN (%Ls)',
        $this->commitPHIDs);
    }

    if ($this->hasPath !== null) {
      if ($this->hasPath) {
        $where[] = qsprintf(
          $conn_r,
          'xcomment.pathID IS NOT NULL');
      } else {
        $where[] = qsprintf(
          $conn_r,
          'xcomment.pathID IS NULL');
      }
    }

    if ($this->pathIDs !== null) {
      $where[] = qsprintf(
        $conn_r,
        'xcomment.pathID IN (%Ld)',
        $this->pathIDs);
    }

    return $where;
  }

}
