<?php

final class PonderAnswerQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $authorPHIDs;
  private $questionIDs;

  private $needViewerVotes;


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

  public function needViewerVotes($need_viewer_votes) {
    $this->needViewerVotes = $need_viewer_votes;
    return $this;
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn,
        'phid IN (%Ls)',
        $this->phids);
    }

    if ($this->authorPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'authorPHID IN (%Ls)',
        $this->authorPHIDs);
    }

    return $where;
  }

  public function newResultObject() {
    return new PonderAnswer();
  }

  protected function loadPage() {
    return $this->loadStandardPage(new PonderAnswer());
  }

  protected function willFilterPage(array $answers) {
    $questions = id(new PonderQuestionQuery())
      ->setViewer($this->getViewer())
      ->withIDs(mpull($answers, 'getQuestionID'))
      ->execute();

    foreach ($answers as $key => $answer) {
      $question = idx($questions, $answer->getQuestionID());
      if (!$question) {
        unset($answers[$key]);
        continue;
      }
      $answer->attachQuestion($question);
    }

    if ($this->needViewerVotes) {
      $viewer_phid = $this->getViewer()->getPHID();

      $etype = PonderAnswerHasVotingUserEdgeType::EDGECONST;
      $edges = id(new PhabricatorEdgeQuery())
        ->withSourcePHIDs(mpull($answers, 'getPHID'))
        ->withDestinationPHIDs(array($viewer_phid))
        ->withEdgeTypes(array($etype))
        ->needEdgeData(true)
        ->execute();
      foreach ($answers as $answer) {
        $user_edge = idx(
          $edges[$answer->getPHID()][$etype],
          $viewer_phid,
          array());
        $answer->attachUserVote($viewer_phid, idx($user_edge, 'data', 0));
      }
    }

    return $answers;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorPonderApplication';
  }

}
