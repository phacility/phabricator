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

final class PhabricatorOwnerPathQuery {

  public static function loadAffectedPaths(
    PhabricatorRepository $repository,
    PhabricatorRepositoryCommit $commit) {

    $drequest = DiffusionRequest::newFromDictionary(
      array(
        'repository'  => $repository,
        'commit'      => $commit->getCommitIdentifier(),
      ));

    $path_query = DiffusionPathChangeQuery::newFromDiffusionRequest(
      $drequest);
    $paths = $path_query->loadChanges();

    $result = array();
    foreach ($paths as $path) {
      $basic_path = '/' . $path->getPath();
      if ($path->getFileType() == DifferentialChangeType::FILE_DIRECTORY) {
        $basic_path = rtrim($basic_path, '/') . '/';
      }
      $result[] = $basic_path;
    }
    return $result;
  }

}
