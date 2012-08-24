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
final class ConduitAPI_paste_query_Method extends ConduitAPI_paste_Method {

  public function getMethodDescription() {
    return "Query Pastes.";
  }

  public function defineParamTypes() {
    return array(
      'ids'           => 'optional list<int>',
      'phids'         => 'optional list<phid>',
      'authorPHIDs'   => 'optional list<phid>',
      'after'         => 'optional int',
      'limit'         => 'optional int, default = 100',
    );
  }

  public function defineReturnType() {
    return 'list<dict>';
  }

  public function defineErrorTypes() {
    return array();
  }

  protected function execute(ConduitAPIRequest $request) {
    $query = id(new PhabricatorPasteQuery())
      ->setViewer($request->getUser())
      ->needContent(true);

    if ($request->getValue('ids')) {
      $query->withIDs($request->getValue('ids'));
    }

    if ($request->getValue('phids')) {
      $query->withPHIDs($request->getValue('phids'));
    }

    if ($request->getValue('authorPHIDs')) {
      $query->withAuthorPHIDs($request->getValue('authorPHIDs'));
    }

    if ($request->getValue('after')) {
      $query->setAfterID($request->getValue('after'));
    }

    $limit = $request->getValue('limit', 100);
    if ($limit) {
      $query->setLimit($limit);
    }

    $pastes = $query->execute();

    $results = array();
    foreach ($pastes as $paste) {
      $results[$paste->getPHID()] = $this->buildPasteInfoDictionary($paste);
    }

    return $results;
  }

}
