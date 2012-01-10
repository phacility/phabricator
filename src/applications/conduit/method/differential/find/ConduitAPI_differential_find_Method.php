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
class ConduitAPI_differential_find_Method extends ConduitAPIMethod {

  public function getMethodDescription() {
    return "Query Differential revisions which match certain criteria.";
  }

  public function defineParamTypes() {
    $types = array(
      DifferentialRevisionListData::QUERY_OPEN_OWNED,
      DifferentialRevisionListData::QUERY_COMMITTABLE,
      DifferentialRevisionListData::QUERY_REVISION_IDS,
      DifferentialRevisionListData::QUERY_PHIDS,
    );

    $types = implode(', ', $types);

    return array(
      'query' => 'required enum<'.$types.'>',
      'guids' => 'required nonempty list<guids>',
    );
  }

  public function defineReturnType() {
    return 'nonempty list<dict>';
  }

  public function defineErrorTypes() {
    return array(
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $query = $request->getValue('query');
    $guids = $request->getValue('guids');

    $results = array();
    if (!$guids) {
      return $results;
    }

    $revisions = id(new DifferentialRevisionListData(
      $query,
      (array)$guids))
      ->loadRevisions();

    foreach ($revisions as $revision) {
      $diff = $revision->loadActiveDiff();
      if (!$diff) {
        continue;
      }
      $id = $revision->getID();
      $results[] = array(
        'id'          => $id,
        'phid'        => $revision->getPHID(),
        'name'        => $revision->getTitle(),
        'uri'         => PhabricatorEnv::getProductionURI('/D'.$id),
        'dateCreated' => $revision->getDateCreated(),
        'authorPHID'  => $revision->getAuthorPHID(),
        'statusName'  =>
          ArcanistDifferentialRevisionStatus::getNameForRevisionStatus(
            $revision->getStatus()),
        'sourcePath'  => $diff->getSourcePath(),
      );
    }

    return $results;
  }

}
