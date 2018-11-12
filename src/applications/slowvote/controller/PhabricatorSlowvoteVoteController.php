<?php

final class PhabricatorSlowvoteVoteController
  extends PhabricatorSlowvoteController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    if (!$request->isFormPost()) {
      return id(new Aphront404Response());
    }

    $poll = id(new PhabricatorSlowvoteQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
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
    $options = mpull($options, null, 'getID');

    $old_votes = $poll->getViewerChoices($viewer);
    $old_votes = mpull($old_votes, null, 'getOptionID');

    $votes = $request->getArr('vote');
    $votes = array_fuse($votes);

    $method = $poll->getMethod();
    $is_plurality = ($method == PhabricatorSlowvotePoll::METHOD_PLURALITY);

    if ($is_plurality && count($votes) > 1) {
      throw new Exception(
        pht('In this poll, you may only vote for one option.'));
    }

    foreach ($votes as $vote) {
      if (!isset($options[$vote])) {
        throw new Exception(
          pht(
            'Option ("%s") is not a valid poll option. You may only '.
            'vote for valid options.',
            $vote));
      }
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
        ->setAuthorPHID($viewer->getPHID())
        ->setPollID($poll->getID())
        ->setOptionID($vote)
        ->save();
    }

    return id(new AphrontRedirectResponse())
      ->setURI($poll->getURI());
  }

}
