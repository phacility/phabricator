<?php

/**
 * @group pholio
 */
final class PholioTransactionQuery
  extends PhabricatorOffsetPagedQuery {

  private $mockIDs;

  public function withMockIDs(array $ids) {
    $this->mockIDs = $ids;
    return $this;
  }

  public function execute() {
    $table = new PholioTransaction();
    $conn_r = $table->establishConnection('r');

    $data = queryfx_all(
      $conn_r,
      'SELECT * FROM %T x %Q %Q %Q',
      $table->getTableName(),
      $this->buildWhereClause($conn_r),
      $this->buildOrderClause($conn_r),
      $this->buildLimitClause($conn_r));

    return $table->loadAllFromArray($data);
  }

  private function buildWhereClause(AphrontDatabaseConnection $conn_r) {
    $where = array();

    if ($this->mockIDs) {
      $where[] = qsprintf(
        $conn_r,
        'mockID IN (%Ld)',
        $this->mockIDs);
    }

    return $this->formatWhereClause($where);
  }

  private function buildOrderClause(AphrontDatabaseConnection $conn_r) {
    return 'ORDER BY id ASC';
  }

}
