<?php

/*
 * Copyright 2011 Facebook, Inc.
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

class ConduitAPI_differential_find_Method extends ConduitAPIMethod {

  public function getMethodDescription() {
    return "Query Differential revisions which match certain criteria.";
  }

  public function defineParamTypes() {
    $types = array(
      DifferentialRevisionListData::QUERY_OPEN_OWNED,
      DifferentialRevisionListData::QUERY_COMMITTABLE,
      DifferentialRevisionListData::QUERY_REVISION_IDS,
      DifferentialRevisionListData::QUERY_BY_PHID,
    );

    $types = implode(', ', $types);

    return array(
      'query' => 'required enum<'.$types.'>',
      'guids' => 'required nonempty list<phid>',
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
    $phids = $request->getValue('guids');

    $revisions = id(new DifferentialRevisionListData(
      $query,
      (array)$phids))
      ->loadRevisions();

    $results = array();
    foreach ($revisions as $revision) {
      $diff = $revision->loadActiveDiff();
      if (!$diff) {
        continue;
      }
      $results[] = array(
        'id'          => $revision->getID(),
        'name'        => $revision->getTitle(),
        'statusName'  => DifferentialRevisionStatus::getNameForRevisionStatus(
          $revision->getStatus()),
        'sourcePath'  => $diff->getSourcePath(),
      );
    }

    return $results;
  }

}
