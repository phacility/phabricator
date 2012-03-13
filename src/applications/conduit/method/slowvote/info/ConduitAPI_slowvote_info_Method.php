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
