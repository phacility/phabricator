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
final class ConduitAPI_chatlog_query_Method
  extends ConduitAPI_chatlog_Method {

  public function getMethodDescription() {
    return "(Unstable!) Retrieve chatter.";
  }

  public function defineParamTypes() {
    return array(
      'channels' => 'optional list<string>',
      'limit'    => 'optional int (default = 100)',
    );
  }

  public function defineReturnType() {
    return 'nonempty list<dict>';
  }

  public function defineErrorTypes() {
    return array();
  }

  protected function execute(ConduitAPIRequest $request) {

    $query = new PhabricatorChatLogQuery();

    $channels = $request->getValue('channels');
    if ($channels) {
      $query->withChannels($channels);
    }

    $limit = $request->getValue('limit');
    if (!$limit) {
      $limit = 100;
    }
    $query->setLimit($limit);

    $logs = $query->execute();

    $results = array();
    foreach ($logs as $log) {
      $results[] = array(
        'channel'       => $log->getChannel(),
        'epoch'         => $log->getEpoch(),
        'author'        => $log->getAuthor(),
        'type'          => $log->getType(),
        'message'       => $log->getMessage(),
        'loggedByPHID'  => $log->getLoggedByPHID(),
      );
    }

    return $results;
  }

}
