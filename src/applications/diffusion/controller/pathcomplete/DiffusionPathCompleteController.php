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

final class DiffusionPathCompleteController extends DiffusionController {

  public function willProcessRequest(array $data) {
    // Don't build a DiffusionRequest.
  }

  public function processRequest() {
    $request = $this->getRequest();

    $repository_phid = $request->getStr('repositoryPHID');
    $repository = id(new PhabricatorRepository())->loadOneWhere(
      'phid = %s',
      $repository_phid);
    if (!$repository) {
      return new Aphront400Response();
    }

    $query_path = $request->getStr('q');
    $query_path = ltrim($query_path, '/');
    if (preg_match('@/$@', $query_path)) {
      $query_dir = $query_path;
    } else {
      $query_dir = dirname($query_path);
      if ($query_dir == '.') {
        $query_dir = '';
      }
    }

    $drequest = DiffusionRequest::newFromDictionary(
      array(
        'repository'  => $repository,
        'path'        => $query_dir,
      ));

    $browse_query = DiffusionBrowseQuery::newFromDiffusionRequest($drequest);
    $paths = $browse_query->loadPaths();

    $output = array();
    foreach ($paths as $path) {
      $full_path = $query_dir.$path->getPath();
      if ($path->getFileType() == DifferentialChangeType::FILE_DIRECTORY) {
        $full_path .= '/';
      }
      $output[] = array('/'.$full_path, null, substr(md5($full_path), 0, 7));
    }

    return id(new AphrontAjaxResponse())->setContent($output);
  }
}
