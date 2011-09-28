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

abstract class PhabricatorRepositoryPullLocalDaemon
  extends PhabricatorRepositoryDaemon {

  abstract protected function getSupportedRepositoryType();
  abstract protected function executeCreate(
    PhabricatorRepository $repository,
    $local_path);
  abstract protected function executeUpdate(
    PhabricatorRepository $repository,
    $local_path);

  final public function run() {
    $repository = $this->loadRepository();
    $expected_type = $this->getSupportedRepositoryType();

    $repo_type = $repository->getVersionControlSystem();
    if ($repo_type != $expected_type) {
      $repo_type_name = PhabricatorRepositoryType::getNameForRepositoryType(
        $repo_type);
      $expected_type_name = PhabricatorRepositoryType::getNameForRepositoryType(
        $expected_type);
      $repo_name = $repository->getName().' ('.$repository->getCallsign().')';
      throw new Exception(
        "This daemon pulls '{$expected_type_name}' repositories, but the ".
        "repository '{$repo_name}' is a '{$repo_type_name}' repository.");
    }

    $tracked = $repository->isTracked();
    if (!$tracked) {
      throw new Exception("Tracking is not enabled for this repository.");
    }

    $local_path = $repository->getDetail('local-path');

    if (!$local_path) {
      throw new Exception("No local path is available for this repository.");
    }

    while (true) {
      if (!Filesystem::pathExists($local_path)) {
        execx('mkdir -p %s', dirname($local_path));
        $this->executeCreate($repository, $local_path);
      } else {
        $this->executeUpdate($repository, $local_path);
      }
      $this->sleep($repository->getDetail('pull-frequency', 15));
    }
  }

}
