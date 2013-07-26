<?php

final class PonderAnswerQuery extends PhabricatorOffsetPagedQuery {

  private $id;
  private $phid;
  private $authorPHID;
  private $orderNewest;

  private $viewer;

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function getViewer() {
    return $this->viewer;
  }

  public function executeOne() {
    return head($this->execute());
  }

  public function withID($qid) {
    $this->id = $qid;
    return $this;
  }

  public function withPHID($phid) {
    $this->phid = $phid;
    return $this;
  }

  public function withAuthorPHID($phid) {
    $this->authorPHID = $phid;
    return $this;
  }

  public function orderByNewest($usethis) {
    $this->orderNewest = $usethis;
    return $this;
  }

  private function buildWhereClause($conn_r) {
    $where = array();
    if ($this->id) {
      $where[] = qsprintf($conn_r, '(id = %d)', $this->id);
    }
    if ($this->phid) {
      $where[] = qsprintf($conn_r, '(phid = %s)', $this->phid);
    }
    if ($this->authorPHID) {
      $where[] = qsprintf($conn_r, '(authorPHID = %s)', $this->authorPHID);
    }

    return $this->formatWhereClause($where);
  }

  private function buildOrderByClause($conn_r) {
    $order = array();
    if ($this->orderNewest) {
      $order[] = qsprintf($conn_r, 'id DESC');
    }

    if (count($order) == 0) {
      $order[] = qsprintf($conn_r, 'id ASC');
    }

    return ($order ? 'ORDER BY ' . implode(', ', $order) : '');
  }

  public function execute() {
    $answer = new PonderAnswer();
    $conn_r = $answer->establishConnection('r');

    $select = qsprintf(
      $conn_r,
      'SELECT r.* FROM %T r',
      $answer->getTableName());

    $where = $this->buildWhereClause($conn_r);
    $order_by = $this->buildOrderByClause($conn_r);
    $limit = $this->buildLimitClause($conn_r);

    return $answer->loadAllFromArray(
      queryfx_all(
        $conn_r,
        '%Q %Q %Q %Q',
        $select,
        $where,
        $order_by,
        $limit));
  }
}
