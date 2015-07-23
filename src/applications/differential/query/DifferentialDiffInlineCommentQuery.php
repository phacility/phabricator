<?php

final class DifferentialDiffInlineCommentQuery
  extends PhabricatorDiffInlineCommentQuery {

  private $revisionPHIDs;

  public function withRevisionPHIDs(array $phids) {
    $this->revisionPHIDs = $phids;
    return $this;
  }

  protected function getTemplate() {
    return new DifferentialTransactionComment();
  }

  protected function buildWhereClauseComponents(
    AphrontDatabaseConnection $conn_r) {
    $where = parent::buildWhereClauseComponents($conn_r);

    if ($this->revisionPHIDs !== null) {
      $where[] = qsprintf(
        $conn_r,
        'revisionPHID IN (%Ls)',
        $this->revisionPHIDs);
    }

    return $where;
  }

}
