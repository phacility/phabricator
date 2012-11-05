<?php

final class PonderQuestionQuery extends PhabricatorOffsetPagedQuery {

  const ORDER_CREATED = 'order-created';
  const ORDER_HOTTEST = 'order-hottest';

  private $ids;
  private $phids;
  private $authorPHIDs;
  private $order = self::ORDER_CREATED;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withAuthorPHIDs(array $phids) {
    $this->authorPHIDs = $phids;
    return $this;
  }

  public function setOrder($order) {
    $this->order = $order;
    return $this;
  }

  public static function loadSingle($viewer, $id) {
    if (!$viewer) {
      throw new Exception("Must set viewer when calling loadSingle");
    }

    return idx(id(new PonderQuestionQuery())
      ->withIDs(array($id))
      ->execute(), $id);
  }

  public static function loadSingleByPHID($viewer, $phid) {
    if (!$viewer) {
      throw new Exception("Must set viewer when calling loadSingle");
    }

    return array_shift(id(new PonderQuestionQuery())
      ->withPHIDs(array($phid))
      ->execute());
  }

  private function buildWhereClause(AphrontDatabaseConnection $conn_r) {
    $where = array();

    if ($this->ids) {
      $where[] = qsprintf($conn_r, 'q.id IN (%Ld)', $this->ids);
    }

    if ($this->phids) {
      $where[] = qsprintf($conn_r, 'q.phid IN (%Ls)', $this->phids);
    }

    if ($this->authorPHIDs) {
      $where[] = qsprintf($conn_r, 'q.authorPHID IN (%Ls)', $this->authorPHIDs);
    }

    return $this->formatWhereClause($where);
  }

  private function buildOrderByClause(AphrontDatabaseConnection $conn_r) {
    switch ($this->order) {
      case self::ORDER_HOTTEST:
        return qsprintf($conn_r, 'ORDER BY q.heat DESC, q.id DESC');
      case self::ORDER_CREATED:
        return qsprintf($conn_r, 'ORDER BY q.id DESC');
      default:
        throw new Exception("Unknown order '{$this->order}'!");
    }
  }

  public function execute() {
    $question = new PonderQuestion();
    $conn_r = $question->establishConnection('r');

    $where = $this->buildWhereClause($conn_r);
    $order_by = $this->buildOrderByClause($conn_r);
    $limit = $this->buildLimitClause($conn_r);

    return $question->loadAllFromArray(
      queryfx_all(
        $conn_r,
        'SELECT q.* FROM %T q %Q %Q %Q',
        $question->getTableName(),
        $where,
        $order_by,
        $limit));
  }


}
