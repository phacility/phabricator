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
final class ConduitAPI_arcanist_projectinfo_Method
  extends ConduitAPI_arcanist_Method {

  public function getMethodDescription() {
    return "Get information about Arcanist projects.";
  }

  public function defineParamTypes() {
    return array(
      'name' => 'required string',
    );
  }

  public function defineReturnType() {
    return 'nonempty dict';
  }

  public function defineErrorTypes() {
    return array(
      'ERR-BAD-ARCANIST-PROJECT' => 'No such project exists.',
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $name = $request->getValue('name');

    $project = id(new PhabricatorRepositoryArcanistProject())->loadOneWhere(
      'name = %s',
      $name);

    if (!$project) {
      throw new ConduitException('ERR-BAD-ARCANIST-PROJECT');
    }

    $repository = $project->loadRepository();

    $repository_phid = null;
    $tracked = false;
    $encoding = null;
    if ($repository) {
      $repository_phid = $repository->getPHID();
      $tracked = $repository->isTracked();
      $encoding = $repository->getDetail('encoding');
    }

    return array(
      'name'            => $project->getName(),
      'phid'            => $project->getPHID(),
      'repositoryPHID'  => $repository_phid,
      'tracked'         => $tracked,
      'encoding'        => $encoding,
    );
  }

}
