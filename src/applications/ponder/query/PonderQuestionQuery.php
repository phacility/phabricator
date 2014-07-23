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

  public function willFilterPage(array $questions) {
    if ($this->needAnswers) {
      $aquery = id(new PonderAnswerQuery())
        ->setViewer($this->getViewer())
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

      $etype = PhabricatorEdgeConfig::TYPE_QUESTION_HAS_VOTING_USER;
      $edges = id(new PhabricatorEdgeQuery())
        ->withSourcePHIDs(mpull($questions, 'getPHID'))
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

  public function getQueryApplicationClass() {
    return 'PhabricatorPonderApplication';
  }

}
