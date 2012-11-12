<?php

final class PonderAnswerQuery extends PhabricatorOffsetPagedQuery {

  private $id;
  private $phid;
  private $authorPHID;
  private $orderNewest;

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

  public static function loadByAuthorWithQuestions(
      $viewer,
      $phid,
      $offset,
      $count) {

    if (!$viewer) {
      throw new Exception("Must set viewer when calling loadByAuthor...");
    }

    $answers = id(new PonderAnswerQuery())
      ->withAuthorPHID($phid)
      ->orderByNewest(true)
      ->setOffset($offset)
      ->setLimit($count)
      ->execute();

    $answerset = new LiskDAOSet();
    foreach ($answers as $answer) {
      $answerset->addToSet($answer);
    }

    foreach ($answers as $answer) {
      $question = $answer->loadOneRelative(
        new PonderQuestion(),
        'id',
        'getQuestionID');
      $answer->setQuestion($question);
    }

    return $answers;
  }

  public static function loadByAuthor($viewer, $author_phid, $offset, $count) {
    if (!$viewer) {
      throw new Exception("Must set viewer when calling loadByAuthor");
    }

    return id(new PonderAnswerQuery())
      ->withAuthorPHID($author_phid)
      ->setOffset($offset)
      ->setLimit($count)
      ->orderByNewest(true)
      ->execute();
  }

  public static function loadSingle($viewer, $id) {
    if (!$viewer) {
      throw new Exception("Must set viewer when calling loadSingle");
    }
    return idx(id(new PonderAnswerQuery())
               ->withID($id)
               ->execute(), $id);
  }

  public static function loadSingleByPHID($viewer, $phid) {
    if (!$viewer) {
      throw new Exception("Must set viewer when calling loadSingle");
    }

    return array_shift(id(new PonderAnswerQuery())
      ->withPHID($phid)
      ->execute());
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
