<?php

/**
 * @group conduit
 */
final class ConduitAPI_slowvote_info_Method extends ConduitAPIMethod {

  public function getMethodDescription() {
    return "Retrieve an array of information about a poll.";
  }

  public function defineParamTypes() {
    return array(
      'poll_id' => 'required id',
    );
  }

  public function defineReturnType() {
    return 'nonempty dict';
  }

  public function defineErrorTypes() {
    return array(
      'ERR_BAD_POLL' => 'No such poll exists',
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $poll_id = $request->getValue('poll_id');
    $poll = id(new PhabricatorSlowvotePoll())->load($poll_id);
    if (!$poll) {
      throw new ConduitException('ERR_BAD_POLL');
    }

    $result = array(
      'id'          => $poll->getID(),
      'phid'        => $poll->getPHID(),
      'authorPHID'  => $poll->getAuthorPHID(),
      'question'    => $poll->getQuestion(),
      'uri'         => PhabricatorEnv::getProductionURI('/V'.$poll->getID()),
    );

    return $result;
  }

}
