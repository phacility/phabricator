<?php

final class PhabricatorTokenCountQuery
  extends PhabricatorOffsetPagedQuery {

  private $objectPHIDs;

  public function withObjectPHIDs(array $object_phids) {
    $this->objectPHIDs = $object_phids;
    return $this;
  }

  public function execute() {
    $table = new PhabricatorTokenCount();
    $conn_r = $table->establishConnection('r');

    $rows = queryfx_all(
      $conn_r,
      'SELECT objectPHID, tokenCount FROM %T %Q %Q',
      $table->getTableName(),
      $this->buildWhereClause($conn_r),
      $this->buildLimitClause($conn_r));

    return ipull($rows, 'tokenCount', 'objectPHID');
  }

  protected function buildWhereClause(AphrontDatabaseConnection $conn_r) {
    $where = array();

    if ($this->objectPHIDs) {
      $where[] = qsprintf(
        $conn_r,
        'objectPHID IN (%Ls)',
        $this->objectPHIDs);
    }

    return $this->formatWhereClause($where);
  }

}
