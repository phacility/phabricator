<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

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
      throw new Exception("Must set votable before saving vote");
    }

    $votable = $this->votable;
    $newvote = $this->vote;

    // prepare vote add, or update if this user is amending an
    // earlier vote
    $editor = id(new PhabricatorEdgeEditor())
      ->setActor($actor)
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
        $curvote = PonderConstants::NONE_VOTE;
      }

      // adjust votable's score by this much
      $delta = $newvote - $curvote;

      queryfx($conn,
        'UPDATE %T as t
        SET t.`voteCount` = t.`voteCount` + %d
        WHERE t.`PHID` = %s',
        $votable->getTableName(),
        $delta,
        $votable->getVotablePHID());

      $editor->save();

    $trans->endReadLocking();
    $trans->saveTransaction();
  }
}
