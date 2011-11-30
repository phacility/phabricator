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

/**
 * @group conduit
 */
class ConduitAPI_differential_getdiff_Method extends ConduitAPIMethod {

  public function getMethodDescription() {
    return "Load the content of a diff from Differential.";
  }

  public function defineParamTypes() {
    return array(
      'revision_id' => 'optional id',
      'diff_id'     => 'optional id',
    );
  }

  public function defineReturnType() {
    return 'nonempty dict';
  }

  public function defineErrorTypes() {
    return array(
      'ERR_BAD_REVISION'    => 'No such revision exists.',
      'ERR_BAD_DIFF'        => 'No such diff exists.',
    );
  }

  public function shouldRequireAuthentication() {
    return !PhabricatorEnv::getEnvConfig('differential.anonymous-access');
  }

  protected function execute(ConduitAPIRequest $request) {
    $diff = null;

    $revision_id = $request->getValue('revision_id');
    if ($revision_id) {
      $revision = id(new DifferentialRevision())->load($revision_id);
      if (!$revision) {
        throw new ConduitException('ERR_BAD_REVISION');
      }
      $diff = id(new DifferentialDiff())->loadOneWhere(
        'revisionID = %d ORDER BY id DESC LIMIT 1',
        $revision->getID());
    } else {
      $diff_id = $request->getValue('diff_id');
      if ($diff_id) {
        $diff = id(new DifferentialDiff())->load($diff_id);
      }
    }

    if (!$diff) {
      throw new ConduitException('ERR_BAD_DIFF');
    }

    $diff->attachChangesets($diff->loadChangesets());
    // TODO: We could batch this to improve performance.
    foreach ($diff->getChangesets() as $changeset) {
      $changeset->attachHunks($changeset->loadHunks());
    }

    $basic_dict = $diff->getDiffDict();

    // for conduit calls, the basic dict is not enough
    // we also need to include the arcanist project
    $project = $diff->loadArcanistProject();
    if ($project) {
      $project_name = $project->getName();
    } else {
      $project_name = null;
    }
    $basic_dict['projectName'] = $project_name;

    return $basic_dict;
  }

}
