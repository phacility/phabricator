<?php

final class PonderAnswerQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $authorPHIDs;
  private $questionIDs;

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

    $where[] = $this->buildPagingClause($conn_r);

    return $this->formatWhereClause($where);
  }

  public function loadPage() {
    $answer = new PonderAnswer();
    $conn_r = $answer->establishConnection('r');

    $data = queryfx_all(
      $conn_r,
      'SELECT a.* FROM %T a %Q %Q %Q',
      $answer->getTableName(),
      $this->buildWhereClause($conn_r),
      $this->buildOrderClause($conn_r),
      $this->buildLimitClause($conn_r));

    return $answer->loadAllFromArray($data);
  }

  public function willFilterPage(array $answers) {
    $questions = id(new PonderQuestionQuery())
      ->setViewer($this->getViewer())
      ->withIDs(mpull($answers, 'getQuestionID'))
      ->execute();

    foreach ($answers as $answer) {
      $question = idx($questions, $answer->getQuestionID());
      $answer->attachQuestion($question);
    }

    return $answers;
  }

  protected function getReversePaging() {
    return true;
  }

}
