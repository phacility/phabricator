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

final class DiffusionLastModifiedController extends DiffusionController {

  public function processRequest() {
    $drequest = $this->getDiffusionRequest();
    $request = $this->getRequest();

    $modified_query = DiffusionLastModifiedQuery::newFromDiffusionRequest(
      $drequest);
    list($commit, $commit_data) = $modified_query->loadLastModification();

    $phids = array();
    if ($commit_data) {
      if ($commit_data->getCommitDetail('authorPHID')) {
        $phids[$commit_data->getCommitDetail('authorPHID')] = true;
      }
      if ($commit_data->getCommitDetail('committerPHID')) {
        $phids[$commit_data->getCommitDetail('committerPHID')] = true;
      }
    }

    $phids = array_keys($phids);
    $handles = $this->loadViewerHandles($phids);

    $output = DiffusionBrowseTableView::renderLastModifiedColumns(
      $drequest->getRepository(),
      $handles,
      $commit,
      $commit_data);

    return id(new AphrontAjaxResponse())
      ->setContent($output);
  }
}
