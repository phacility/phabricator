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
final class ConduitAPI_differential_creatediff_Method extends ConduitAPIMethod {

  public function getMethodDescription() {
    return "Create a new Differential diff.";
  }

  public function defineParamTypes() {
    return array(
      'changes'                   => 'required list<dict>',
      'sourceMachine'             => 'required string',
      'sourcePath'                => 'required string',
      'branch'                    => 'required string',
      'sourceControlSystem'       => 'required enum<svn, git>',
      'sourceControlPath'         => 'required string',
      'sourceControlBaseRevision' => 'required string',
      'parentRevisionID'          => 'optional revisionid',
      'creationMethod'            => 'optional string',
      'authorPHID'                => 'optional phid',
      'arcanistProject'           => 'optional string',
      'repositoryUUID'            => 'optional string',
      'lintStatus'                =>
        'required enum<none, skip, okay, warn, fail>',
      'unitStatus'                =>
        'required enum<none, skip, okay, warn, fail>',
    );
  }

  public function defineReturnType() {
    return 'nonempty dict';
  }

  public function defineErrorTypes() {
    return array(
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $change_data = $request->getValue('changes');

    $changes = array();
    foreach ($change_data as $dict) {
      $changes[] = ArcanistDiffChange::newFromDictionary($dict);
    }

    $diff = DifferentialDiff::newFromRawChanges($changes);
    $diff->setSourcePath($request->getValue('sourcePath'));
    $diff->setSourceMachine($request->getValue('sourceMachine'));

    $diff->setBranch($request->getValue('branch'));
    $diff->setCreationMethod($request->getValue('creationMethod'));
    $diff->setAuthorPHID($request->getValue('authorPHID'));

    $parent_id = $request->getValue('parentRevisionID');
    if ($parent_id) {
      $parent_rev = id(new DifferentialRevision())->load($parent_id);
      if ($parent_rev) {
        if ($parent_rev->getStatus() !=
            ArcanistDifferentialRevisionStatus::CLOSED) {
          $diff->setParentRevisionID($parent_id);
        }
      }
    }

    $system = $request->getValue('sourceControlSystem');
    $diff->setSourceControlSystem($system);
    $diff->setSourceControlPath($request->getValue('sourceControlPath'));
    $diff->setSourceControlBaseRevision(
      $request->getValue('sourceControlBaseRevision'));

    $project_name = $request->getValue('arcanistProject');
    $project_phid = null;
    if ($project_name) {
      $arcanist_project = id(new PhabricatorRepositoryArcanistProject())
        ->loadOneWhere(
          'name = %s',
          $project_name);
      if (!$arcanist_project) {
        $arcanist_project = new PhabricatorRepositoryArcanistProject();
        $arcanist_project->setName($project_name);
        $arcanist_project->save();
      }
      $project_phid = $arcanist_project->getPHID();
    }

    $diff->setArcanistProjectPHID($project_phid);
    $diff->setRepositoryUUID($request->getValue('repositoryUUID'));

    switch ($request->getValue('lintStatus')) {
      case 'skip':
        $diff->setLintStatus(DifferentialLintStatus::LINT_SKIP);
        break;
      case 'okay':
        $diff->setLintStatus(DifferentialLintStatus::LINT_OKAY);
        break;
      case 'warn':
        $diff->setLintStatus(DifferentialLintStatus::LINT_WARN);
        break;
      case 'fail':
        $diff->setLintStatus(DifferentialLintStatus::LINT_FAIL);
        break;
      case 'none':
      default:
        $diff->setLintStatus(DifferentialLintStatus::LINT_NONE);
        break;
    }

    switch ($request->getValue('unitStatus')) {
      case 'skip':
        $diff->setUnitStatus(DifferentialUnitStatus::UNIT_SKIP);
        break;
      case 'okay':
        $diff->setUnitStatus(DifferentialUnitStatus::UNIT_OKAY);
        break;
      case 'warn':
        $diff->setUnitStatus(DifferentialUnitStatus::UNIT_WARN);
        break;
      case 'fail':
        $diff->setUnitStatus(DifferentialUnitStatus::UNIT_FAIL);
        break;
      case 'postponed':
        $diff->setUnitStatus(DifferentialUnitStatus::UNIT_POSTPONED);
        break;
      case 'none':
      default:
        $diff->setUnitStatus(DifferentialUnitStatus::UNIT_NONE);
        break;
    }

    $diff->save();

    $path = '/differential/diff/'.$diff->getID().'/';
    $uri = PhabricatorEnv::getURI($path);

    return array(
      'diffid' => $diff->getID(),
      'uri'    => $uri,
    );
  }

}
