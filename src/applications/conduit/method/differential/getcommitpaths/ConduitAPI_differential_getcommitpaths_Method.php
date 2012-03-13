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
final class ConduitAPI_differential_getcommitpaths_Method
  extends ConduitAPIMethod {

  public function getMethodDescription() {
    return "Query which paths should be included when committing a ".
           "Differential revision.";
  }

  public function defineParamTypes() {
    return array(
      'revision_id' => 'required int',
    );
  }

  public function defineReturnType() {
    return 'nonempty list<string>';
  }

  public function defineErrorTypes() {
    return array(
      'ERR_NOT_FOUND' => 'No such revision exists.',
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $id = $request->getValue('revision_id');

    $revision = id(new DifferentialRevision())->load($id);
    if (!$revision) {
      throw new ConduitException('ERR_NOT_FOUND');
    }

    $paths = array();
    $diff = id(new DifferentialDiff())->loadOneWhere(
      'revisionID = %d ORDER BY id DESC limit 1',
      $revision->getID());

    $diff->attachChangesets($diff->loadChangesets());

    foreach ($diff->getChangesets() as $changeset) {
      $paths[] = $changeset->getFilename();
    }

    return $paths;
  }

}
