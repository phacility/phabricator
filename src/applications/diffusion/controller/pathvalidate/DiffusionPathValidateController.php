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

final class DiffusionPathValidateController extends DiffusionController {

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

    $path = $request->getStr('path');
    $path = ltrim($path, '/');

    $drequest = DiffusionRequest::newFromDictionary(
      array(
        'repository'  => $repository,
        'path'        => $path,
      ));

    $browse_query = DiffusionBrowseQuery::newFromDiffusionRequest($drequest);
    $browse_query->needValidityOnly(true);
    $valid = $browse_query->loadPaths();

    if (!$valid) {
      switch ($browse_query->getReasonForEmptyResultSet()) {
        case DiffusionBrowseQuery::REASON_IS_FILE:
          $valid = true;
          break;
        case DiffusionBrowseQuery::REASON_IS_EMPTY:
          $valid = true;
          break;
      }
    }

    $output = array(
      'valid' => (bool)$valid,
    );

    if (!$valid) {
      $branch = $drequest->getBranch();
      if ($branch) {
        $message = 'Not found in '.$branch;
      } else {
        $message = 'Not found at HEAD';
      }
    } else {
      $message = 'OK';
    }

    $output['message'] = $message;

    return id(new AphrontAjaxResponse())->setContent($output);
  }
}
