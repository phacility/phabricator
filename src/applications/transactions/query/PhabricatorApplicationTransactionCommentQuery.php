<?php

abstract class PhabricatorApplicationTransactionCommentQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $authorPHIDs;
  private $phids;
  private $transactionPHIDs;
  private $isDeleted;
  private $hasTransaction;

  abstract protected function getTemplate();

  public function withIDs(array $ids) {
    $this->ids = $ids;
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

  public function withAuthorPHIDs(array $phids) {
    $this->authorPHIDs = $phids;
    return $this;
  }

  public function withIsDeleted($deleted) {
    $this->isDeleted = $deleted;
    return $this;
  }

  public function withHasTransaction($has_transaction) {
    $this->hasTransaction = $has_transaction;
    return $this;
  }

  protected function loadPage() {
    $table = $this->getTemplate();
    $conn_r = $table->establishConnection('r');

    $data = queryfx_all(
      $conn_r,
      'SELECT * FROM %T xcomment %Q %Q %Q',
      $table->getTableName(),
      $this->buildWhereClause($conn_r),
      $this->buildOrderClause($conn_r),
      $this->buildLimitClause($conn_r));

    return $table->loadAllFromArray($data);
  }

  protected function buildWhereClause(AphrontDatabaseConnection $conn) {
    return $this->formatWhereClause(
      $conn,
      $this->buildWhereClauseComponents($conn));
  }

  protected function buildWhereClauseComponents(
    AphrontDatabaseConnection $conn) {

    $where = array();

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'xcomment.id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn,
        'xcomment.phid IN (%Ls)',
        $this->phids);
    }

    if ($this->authorPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'xcomment.authorPHID IN (%Ls)',
        $this->authorPHIDs);
    }

    if ($this->transactionPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'xcomment.transactionPHID IN (%Ls)',
        $this->transactionPHIDs);
    }

    if ($this->isDeleted !== null) {
      $where[] = qsprintf(
        $conn,
        'xcomment.isDeleted = %d',
        (int)$this->isDeleted);
    }

    if ($this->hasTransaction !== null) {
      if ($this->hasTransaction) {
        $where[] = qsprintf(
          $conn,
          'xcomment.transactionPHID IS NOT NULL');
      } else {
        $where[] = qsprintf(
          $conn,
          'xcomment.transactionPHID IS NULL');
      }
    }

    return $where;
  }

  public function getQueryApplicationClass() {
    // TODO: Figure out the app via the template?
    return null;
  }


}
