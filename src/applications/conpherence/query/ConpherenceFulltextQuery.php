<?php

final class ConpherenceFulltextQuery
  extends PhabricatorOffsetPagedQuery {

  private $threadPHIDs;
  private $previousTransactionPHIDs;
  private $fulltext;

  public function withThreadPHIDs(array $phids) {
    $this->threadPHIDs = $phids;
    return $this;
  }

  public function withPreviousTransactionPHIDs(array $phids) {
    $this->previousTransactionPHIDs = $phids;
    return $this;
  }

  public function withFulltext($fulltext) {
    $this->fulltext = $fulltext;
    return $this;
  }

  public function execute() {
    $table = new ConpherenceIndex();
    $conn_r = $table->establishConnection('r');

    $rows = queryfx_all(
      $conn_r,
      'SELECT threadPHID, transactionPHID, previousTransactionPHID
        FROM %T i %Q %Q %Q',
      $table->getTableName(),
      $this->buildWhereClause($conn_r),
      $this->buildOrderByClause($conn_r),
      $this->buildLimitClause($conn_r));

    return $rows;
  }

  protected function buildWhereClause(AphrontDatabaseConnection $conn_r) {
    $where = array();

    if ($this->threadPHIDs !== null) {
      $where[] = qsprintf(
        $conn_r,
        'i.threadPHID IN (%Ls)',
        $this->threadPHIDs);
    }

    if ($this->previousTransactionPHIDs !== null) {
      $where[] = qsprintf(
        $conn_r,
        'i.previousTransactionPHID IN (%Ls)',
        $this->previousTransactionPHIDs);
    }

    if (strlen($this->fulltext)) {
      $where[] = qsprintf(
        $conn_r,
        'MATCH(i.corpus) AGAINST (%s IN BOOLEAN MODE)',
        $this->fulltext);
    }

    return $this->formatWhereClause($where);
  }

  private function buildOrderByClause(AphrontDatabaseConnection $conn_r) {
    if (strlen($this->fulltext)) {
      return qsprintf(
        $conn_r,
        'ORDER BY MATCH(i.corpus) AGAINST (%s IN BOOLEAN MODE) DESC',
        $this->fulltext);
    } else {
      return qsprintf(
        $conn_r,
        'ORDER BY id DESC');
    }
  }

}
