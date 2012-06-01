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
final class ConduitAPI_differential_getalldiffs_Method
  extends ConduitAPIMethod {

  public function getMethodDescription() {
    return "Load all diffs for given revisions from Differential.";
  }

  public function defineParamTypes() {
    return array(
      'revision_ids' => 'required list<int>',
    );
  }

  public function defineReturnType() {
    return 'dict';
  }

  public function defineErrorTypes() {
    return array();
  }

  protected function execute(ConduitAPIRequest $request) {
    $results = array();
    $revision_ids = $request->getValue('revision_ids');

    if (!$revision_ids) {
      return $results;
    }

    $diffs = id(new DifferentialDiff())->loadAllWhere(
      'revisionID IN (%Ld)',
      $revision_ids);

    foreach ($diffs as $diff) {
      $results[] = array(
        'revision_id' => $diff->getRevisionID(),
        'diff_id' => $diff->getID(),
      );
    }

    return $results;
  }
}
