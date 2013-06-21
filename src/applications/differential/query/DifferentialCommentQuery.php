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

    return $table->loadAllFromArray($data);
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

}
