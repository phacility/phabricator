<?php

final class SlowvoteInfoConduitAPIMethod extends SlowvoteConduitAPIMethod {

  public function getAPIMethodName() {
    return 'slowvote.info';
  }

  public function getMethodStatus() {
    return self::METHOD_STATUS_DEPRECATED;
  }

  public function getMethodStatusDescription() {
    return pht('Replaced by "slowvote.poll.search".');
  }

  public function getMethodDescription() {
    return pht('Retrieve an array of information about a poll.');
  }

  protected function defineParamTypes() {
    return array(
      'poll_id' => 'required id',
    );
  }

  protected function defineReturnType() {
    return 'nonempty dict';
  }

  protected function defineErrorTypes() {
    return array(
      'ERR_BAD_POLL' => pht('No such poll exists.'),
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $viewer = $this->getViewer();

    $poll_id = $request->getValue('poll_id');

    $poll = id(new PhabricatorSlowvoteQuery())
      ->setViewer($viewer)
      ->withIDs(array($poll_id))
      ->executeOne();
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
