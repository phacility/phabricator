<?php

abstract class PhabricatorApplicationTransactionCommentQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $authorPHIDs;
  private $phids;
  private $transactionPHIDs;
  private $isDeleted;
  private $hasTransaction;

  abstract protected function newApplicationTransactionCommentTemplate();

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

  public function newResultObject() {
    return $this->newApplicationTransactionCommentTemplate();
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);
    $alias = $this->getPrimaryTableAlias();

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        '%T.id IN (%Ld)',
        $alias,
        $this->ids);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn,
        '%T.phid IN (%Ls)',
        $alias,
        $this->phids);
    }

    if ($this->authorPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        '%T.authorPHID IN (%Ls)',
        $alias,
        $this->authorPHIDs);
    }

    if ($this->transactionPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        '%T.transactionPHID IN (%Ls)',
        $alias,
        $this->transactionPHIDs);
    }

    if ($this->isDeleted !== null) {
      $where[] = qsprintf(
        $conn,
        '%T.isDeleted = %d',
        $alias,
        (int)$this->isDeleted);
    }

    if ($this->hasTransaction !== null) {
      if ($this->hasTransaction) {
        $where[] = qsprintf(
          $conn,
          '%T.transactionPHID IS NOT NULL',
          $alias);
      } else {
        $where[] = qsprintf(
          $conn,
          '%T.transactionPHID IS NULL',
          $alias);
      }
    }

    return $where;
  }

  protected function getPrimaryTableAlias() {
    return 'xcomment';
  }

  public function getQueryApplicationClass() {
    // TODO: Figure out the app via the template?
    return null;
  }

}
