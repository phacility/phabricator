<?php

final class PhabricatorSlowvoteQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $authorPHIDs;
  private $withVotesByViewer;
  private $isClosed;

  private $needOptions;
  private $needChoices;
  private $needViewerChoices;

  public function withIDs($ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs($phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withAuthorPHIDs($author_phids) {
    $this->authorPHIDs = $author_phids;
    return $this;
  }

  public function withVotesByViewer($with_vote) {
    $this->withVotesByViewer = $with_vote;
    return $this;
  }

  public function withIsClosed($with_closed) {
    $this->isClosed = $with_closed;
    return $this;
  }

  public function needOptions($need_options) {
    $this->needOptions = $need_options;
    return $this;
  }

  public function needChoices($need_choices) {
    $this->needChoices = $need_choices;
    return $this;
  }

  public function needViewerChoices($need_viewer_choices) {
    $this->needViewerChoices = $need_viewer_choices;
    return $this;
  }

  public function newResultObject() {
    return new PhabricatorSlowvotePoll();
  }

  protected function loadPage() {
    return $this->loadStandardPage($this->newResultObject());
  }

  protected function willFilterPage(array $polls) {
    assert_instances_of($polls, 'PhabricatorSlowvotePoll');

    $ids = mpull($polls, 'getID');
    $viewer = $this->getViewer();

    if ($this->needOptions) {
      $options = id(new PhabricatorSlowvoteOption())->loadAllWhere(
        'pollID IN (%Ld)',
        $ids);

      $options = mgroup($options, 'getPollID');
      foreach ($polls as $poll) {
        $poll->attachOptions(idx($options, $poll->getID(), array()));
      }
    }

    if ($this->needChoices) {
      $choices = id(new PhabricatorSlowvoteChoice())->loadAllWhere(
        'pollID IN (%Ld)',
        $ids);

      $choices = mgroup($choices, 'getPollID');
      foreach ($polls as $poll) {
        $poll->attachChoices(idx($choices, $poll->getID(), array()));
      }

      // If we need the viewer's choices, we can just fill them from the data
      // we already loaded.
      if ($this->needViewerChoices) {
        foreach ($polls as $poll) {
          $poll->attachViewerChoices(
            $viewer,
            idx(
              mgroup($poll->getChoices(), 'getAuthorPHID'),
              $viewer->getPHID(),
              array()));
        }
      }
    } else if ($this->needViewerChoices) {
      $choices = id(new PhabricatorSlowvoteChoice())->loadAllWhere(
        'pollID IN (%Ld) AND authorPHID = %s',
        $ids,
        $viewer->getPHID());

      $choices = mgroup($choices, 'getPollID');
      foreach ($polls as $poll) {
        $poll->attachViewerChoices(
          $viewer,
          idx($choices, $poll->getID(), array()));
      }
    }

    return $polls;
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'p.id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn,
        'p.phid IN (%Ls)',
        $this->phids);
    }

    if ($this->authorPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'p.authorPHID IN (%Ls)',
        $this->authorPHIDs);
    }

    if ($this->isClosed !== null) {
      $where[] = qsprintf(
        $conn,
        'p.isClosed = %d',
        (int)$this->isClosed);
    }
    return $where;
  }

  protected function buildJoinClauseParts(AphrontDatabaseConnection $conn) {
    $joins = parent::buildJoinClauseParts($conn);

    if ($this->withVotesByViewer !== null) {
      $joins[] = qsprintf(
        $conn,
        'JOIN %T vv ON vv.pollID = p.id AND vv.authorPHID = %s',
        id(new PhabricatorSlowvoteChoice())->getTableName(),
        $this->getViewer()->getPHID());
    }
    return $joins;
  }

  protected function getPrimaryTableAlias() {
    return 'p';
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorSlowvoteApplication';
  }

}
