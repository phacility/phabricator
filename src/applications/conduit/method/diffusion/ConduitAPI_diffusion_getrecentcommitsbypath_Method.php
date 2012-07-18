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
final class ConduitAPI_diffusion_getrecentcommitsbypath_Method
  extends ConduitAPIMethod {

  const DEFAULT_LIMIT = 10;

  public function getMethodDescription() {
    return "Get commit identifiers for recent commits affecting a given path.";
  }

  public function defineParamTypes() {
    return array(
      'callsign' => 'required string',
      'path' => 'required string',
      'limit' => 'optional int',
    );
  }

  public function defineReturnType() {
    return 'nonempty list<string>';
  }

  public function defineErrorTypes() {
    return array(
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $drequest = DiffusionRequest::newFromDictionary(
      array(
        'callsign'  => $request->getValue('callsign'),
        'path'      => $request->getValue('path'),
      ));

    $limit = nonempty(
      $request->getValue('limit'),
      self::DEFAULT_LIMIT
    );

    $history = DiffusionHistoryQuery::newFromDiffusionRequest($drequest)
    ->setLimit($limit)
    ->needDirectChanges(true)
    ->needChildChanges(true)
    ->loadHistory();

    $raw_commit_identifiers = mpull($history, 'getCommitIdentifier');
    $result = array();
    foreach ($raw_commit_identifiers as $id) {
      $result[] = 'r'.$request->getValue('callsign').$id;
    }
    return $result;
  }
}
