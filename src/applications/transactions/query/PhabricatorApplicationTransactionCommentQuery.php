<?php

final class PhabricatorApplicationTransactionCommentQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $template;

  private $phids;
  private $transactionPHIDs;

  public function setTemplate(
    PhabricatorApplicationTransactionComment $template) {
    $this->template = $template;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withTransactionPHIDs(array $transaction_phids) {
    $this->transactionPHIDs = $transaction_phids;
    return $this;
  }

  protected function loadPage() {
    $table = $this->template;
    $conn_r = $table->establishConnection('r');

    $data = queryfx_all(
      $conn_r,
      'SELECT * FROM %T xc %Q %Q %Q',
      $table->getTableName(),
      $this->buildWhereClause($conn_r),
      $this->buildOrderClause($conn_r),
      $this->buildLimitClause($conn_r));

    return $table->loadAllFromArray($data);
  }

  private function buildWhereClause(AphrontDatabaseConnection $conn_r) {
    $where = array();

    if ($this->phids) {
      $where[] = qsprintf(
        $conn_r,
        'phid IN (%Ls)',
        $this->phids);
    }

    if ($this->transactionPHIDs) {
      $where[] = qsprintf(
        $conn_r,
        'transactionPHID IN (%Ls)',
        $this->transactionPHIDs);
    }

    return $this->formatWhereClause($where);
  }

}
