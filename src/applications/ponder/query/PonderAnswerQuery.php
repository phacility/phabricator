<?php

final class PonderAnswerQuery extends PhabricatorOffsetPagedQuery {

  private $ids;
  private $phids;
  private $authorPHIDs;
  private $questionIDs;

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

  public function withQuestionIDs(array $ids) {
    $this->questionIDs = $ids;
    return $this;
  }

  private function buildWhereClause($conn_r) {
    $where = array();

    if ($this->ids) {
      $where[] = qsprintf(
        $conn_r,
        'id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids) {
      $where[] = qsprintf(
        $conn_r,
        'phid IN (%Ls)',
        $this->phids);
    }

    if ($this->authorPHIDs) {
      $where[] = qsprintf(
        $conn_r,
        'authorPHID IN (%Ls)',
        $this->authorPHIDs);
    }

    return $this->formatWhereClause($where);
  }

  private function buildOrderByClause($conn_r) {
    return 'ORDER BY id ASC';
  }

  public function execute() {
    $answer = new PonderAnswer();
    $conn_r = $answer->establishConnection('r');

    $data = queryfx_all(
      $conn_r,
      'SELECT a.* FROM %T a %Q %Q %Q',
      $answer->getTableName(),
      $this->buildWhereClause($conn_r),
      $this->buildOrderByClause($conn_r),
      $this->buildLimitClause($conn_r));

    $answers = $answer->loadAllFromArray($data);

    if ($answers) {
      $questions = id(new PonderQuestionQuery())
        ->setViewer($this->getViewer())
        ->withIDs(mpull($answers, 'getQuestionID'))
        ->execute();

      foreach ($answers as $answer) {
        $question = idx($questions, $answer->getQuestionID());
        $answer->attachQuestion($question);
      }
    }

    return $answers;
  }
}
