<?php

final class PonderQuestionQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  const ORDER_CREATED = 'order-created';
  const ORDER_HOTTEST = 'order-hottest';

  private $ids;
  private $phids;
  private $authorPHIDs;
  private $answererPHIDs;
  private $order = self::ORDER_CREATED;

  private $status = 'status-any';
  const STATUS_ANY      = 'status-any';
  const STATUS_OPEN     = 'status-open';
  const STATUS_CLOSED   = 'status-closed';

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

  public function withStatus($status) {
    $this->status = $status;
    return $this;
  }

  public function withAnswererPHIDs(array $phids) {
    $this->answererPHIDs = $phids;
    return $this;
  }

  public function setOrder($order) {
    $this->order = $order;
    return $this;
  }

  private function buildWhereClause(AphrontDatabaseConnection $conn_r) {
    $where = array();

    if ($this->ids) {
      $where[] = qsprintf(
        $conn_r,
        'q.id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids) {
      $where[] = qsprintf(
        $conn_r,
        'q.phid IN (%Ls)',
        $this->phids);
    }

    if ($this->authorPHIDs) {
      $where[] = qsprintf(
        $conn_r,
        'q.authorPHID IN (%Ls)',
        $this->authorPHIDs);
    }

    if ($this->status) {
      switch ($this->status) {
        case self::STATUS_ANY:
          break;
        case self::STATUS_OPEN:
          $where[] = qsprintf(
            $conn_r,
            'q.status = %d',
            PonderQuestionStatus::STATUS_OPEN);
          break;
        case self::STATUS_CLOSED:
          $where[] = qsprintf(
            $conn_r,
            'q.status = %d',
            PonderQuestionStatus::STATUS_CLOSED);
          break;
        default:
          throw new Exception("Unknown status query '{$this->status}'!");
      }
    }

    $where[] = $this->buildPagingClause($conn_r);

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

  protected function loadPage() {
    $question = new PonderQuestion();
    $conn_r = $question->establishConnection('r');

    $data = queryfx_all(
      $conn_r,
      'SELECT q.* FROM %T q %Q %Q %Q %Q',
      $question->getTableName(),
      $this->buildJoinsClause($conn_r),
      $this->buildWhereClause($conn_r),
      $this->buildOrderByClause($conn_r),
      $this->buildLimitClause($conn_r));

    return $question->loadAllFromArray($data);
  }

  private function buildJoinsClause(AphrontDatabaseConnection $conn_r) {
    $joins = array();

    if ($this->answererPHIDs) {
      $answer_table = new PonderAnswer();
      $joins[] = qsprintf(
        $conn_r,
        'JOIN %T a ON a.questionID = q.id AND a.authorPHID IN (%Ls)',
        $answer_table->getTableName(),
        $this->answererPHIDs);
    }

    return implode(' ', $joins);
  }

}
