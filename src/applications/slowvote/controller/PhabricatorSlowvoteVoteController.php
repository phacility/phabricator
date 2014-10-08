<?php

final class PhabricatorSlowvoteVoteController
  extends PhabricatorSlowvoteController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $poll = id(new PhabricatorSlowvoteQuery())
      ->setViewer($user)
      ->withIDs(array($this->id))
      ->needOptions(true)
      ->needViewerChoices(true)
      ->executeOne();
    if (!$poll) {
      return new Aphront404Response();
    }
    if ($poll->getIsClosed()) {
      return new Aphront400Response();
    }

    $options = $poll->getOptions();
    $user_choices = $poll->getViewerChoices($user);

    $old_votes = mpull($user_choices, null, 'getOptionID');

    if ($request->isAjax()) {
      $vote = $request->getInt('vote');
      $votes = array_keys($old_votes);
      $votes = array_fuse($votes, $votes);

      if ($poll->getMethod() == PhabricatorSlowvotePoll::METHOD_PLURALITY) {
        if (idx($votes, $vote, false)) {
          $votes = array();
        } else {
          $votes = array($vote);
        }
      } else {
        if (idx($votes, $vote, false)) {
          unset($votes[$vote]);
        } else {
          $votes[$vote] = $vote;
        }
      }

      $this->updateVotes($user, $poll, $old_votes, $votes);

      $updated_choices = id(new PhabricatorSlowvoteChoice())->loadAllWhere(
        'pollID = %d AND authorPHID = %s',
        $poll->getID(),
        $user->getPHID());

      $embed = id(new SlowvoteEmbedView())
        ->setPoll($poll)
        ->setOptions($options)
        ->setViewerChoices($updated_choices);

      return id(new AphrontAjaxResponse())
        ->setContent(array(
          'pollID' => $poll->getID(),
          'contentHTML' => $embed->render(),
        ));
    }

    if (!$request->isFormPost()) {
      return id(new Aphront404Response());
    }

    $votes = $request->getArr('vote');
    $votes = array_fuse($votes, $votes);

    $this->updateVotes($user, $poll, $old_votes, $votes);

    return id(new AphrontRedirectResponse())->setURI('/V'.$poll->getID());
  }

  private function updateVotes($user, $poll, $old_votes, $votes) {
    if (!empty($votes) && count($votes) > 1 &&
        $poll->getMethod() == PhabricatorSlowvotePoll::METHOD_PLURALITY) {
      return id(new Aphront400Response());
    }

    foreach ($old_votes as $old_vote) {
      if (!idx($votes, $old_vote->getOptionID(), false)) {
        $old_vote->delete();
      }
    }

    foreach ($votes as $vote) {
      if (idx($old_votes, $vote, false)) {
        continue;
      }

      id(new PhabricatorSlowvoteChoice())
        ->setAuthorPHID($user->getPHID())
        ->setPollID($poll->getID())
        ->setOptionID($vote)
        ->save();
    }
  }

}
