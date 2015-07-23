<?php

final class PonderVoteEditor extends PhabricatorEditor {

  private $answer;
  private $votable;
  private $anwer;
  private $vote;

  public function setAnswer($answer) {
    $this->answer = $answer;
    return $this;
  }

  public function setVotable($votable) {
    $this->votable = $votable;
    return $this;
  }

  public function setVote($vote) {
    $this->vote = $vote;
    return $this;
  }

  public function saveVote() {
    $actor = $this->requireActor();
    if (!$this->votable) {
      throw new PhutilInvalidStateException('setVotable');
    }

    $votable = $this->votable;
    $newvote = $this->vote;

    // prepare vote add, or update if this user is amending an
    // earlier vote
    $editor = id(new PhabricatorEdgeEditor())
      ->addEdge(
        $actor->getPHID(),
        $votable->getUserVoteEdgeType(),
        $votable->getVotablePHID(),
        array('data' => $newvote))
      ->removeEdge(
        $actor->getPHID(),
        $votable->getUserVoteEdgeType(),
        $votable->getVotablePHID());

    $conn = $votable->establishConnection('w');
    $trans = $conn->openTransaction();
    $trans->beginReadLocking();

      $votable->reload();
      $curvote = (int)PhabricatorEdgeQuery::loadSingleEdgeData(
        $actor->getPHID(),
        $votable->getUserVoteEdgeType(),
        $votable->getVotablePHID());

      if (!$curvote) {
        $curvote = PonderVote::VOTE_NONE;
      }

      // Adjust votable's score by this much.
      $delta = $newvote - $curvote;

      queryfx($conn,
        'UPDATE %T as t
        SET t.voteCount = t.voteCount + %d
        WHERE t.PHID = %s',
        $votable->getTableName(),
        $delta,
        $votable->getVotablePHID());

      $editor->save();

    $trans->endReadLocking();
    $trans->saveTransaction();
  }
}
