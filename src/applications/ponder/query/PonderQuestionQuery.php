<?php

final class PonderQuestionQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $authorPHIDs;
  private $answererPHIDs;

  private $needProjectPHIDs;

  private $status = 'status-any';

  const STATUS_ANY      = 'status-any';
  const STATUS_OPEN     = 'status-open';
  const STATUS_CLOSED   = 'status-closed';

  private $needAnswers;
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

  public function withStatus($status) {
    $this->status = $status;
    return $this;
  }

  public function withAnswererPHIDs(array $phids) {
    $this->answererPHIDs = $phids;
    return $this;
  }

  public function needAnswers($need_answers) {
    $this->needAnswers = $need_answers;
    return $this;
  }

  public function needViewerVotes($need_viewer_votes) {
    $this->needViewerVotes = $need_viewer_votes;
    return $this;
  }

  public function needProjectPHIDs($need_projects) {
    $this->needProjectPHIDs = $need_projects;
    return $this;
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'q.id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn,
        'q.phid IN (%Ls)',
        $this->phids);
    }

    if ($this->authorPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'q.authorPHID IN (%Ls)',
        $this->authorPHIDs);
    }

    if ($this->status !== null) {
      switch ($this->status) {
        case self::STATUS_ANY:
          break;
        case self::STATUS_OPEN:
          $where[] = qsprintf(
            $conn,
            'q.status = %d',
            PonderQuestionStatus::STATUS_OPEN);
          break;
        case self::STATUS_CLOSED:
          $where[] = qsprintf(
            $conn,
            'q.status = %d',
            PonderQuestionStatus::STATUS_CLOSED);
          break;
        default:
          throw new Exception(pht("Unknown status query '%s'!", $this->status));
      }
    }

    return $where;
  }

  public function newResultObject() {
    return new PonderQuestion();
  }

  protected function loadPage() {
    return $this->loadStandardPage(new PonderQuestion());
  }

  protected function willFilterPage(array $questions) {

    $phids = mpull($questions, 'getPHID');

    if ($this->needAnswers) {
      $aquery = id(new PonderAnswerQuery())
        ->setViewer($this->getViewer())
        ->setOrderVector(array('-id'))
        ->withQuestionIDs(mpull($questions, 'getID'));

      if ($this->needViewerVotes) {
        $aquery->needViewerVotes($this->needViewerVotes);
      }

      $answers = $aquery->execute();
      $answers = mgroup($answers, 'getQuestionID');

      foreach ($questions as $question) {
        $question_answers = idx($answers, $question->getID(), array());
        $question->attachAnswers(mpull($question_answers, null, 'getPHID'));
      }
    }

    if ($this->needViewerVotes) {
      $viewer_phid = $this->getViewer()->getPHID();

      $etype = PonderQuestionHasVotingUserEdgeType::EDGECONST;
      $edges = id(new PhabricatorEdgeQuery())
        ->withSourcePHIDs($phids)
        ->withDestinationPHIDs(array($viewer_phid))
        ->withEdgeTypes(array($etype))
        ->needEdgeData(true)
        ->execute();
      foreach ($questions as $question) {
        $user_edge = idx(
          $edges[$question->getPHID()][$etype],
          $viewer_phid,
          array());

        $question->attachUserVote($viewer_phid, idx($user_edge, 'data', 0));
      }
    }

    if ($this->needProjectPHIDs) {
      $edge_query = id(new PhabricatorEdgeQuery())
        ->withSourcePHIDs($phids)
        ->withEdgeTypes(
          array(
            PhabricatorProjectObjectHasProjectEdgeType::EDGECONST,
          ));
      $edge_query->execute();

      foreach ($questions as $question) {
        $project_phids = $edge_query->getDestinationPHIDs(
          array($question->getPHID()));
        $question->attachProjectPHIDs($project_phids);
      }
    }

    return $questions;
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

  protected function getPrimaryTableAlias() {
    return 'q';
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorPonderApplication';
  }

}
