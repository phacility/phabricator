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

final class DiffusionBrowseController extends DiffusionController {

  public function processRequest() {
    $drequest = $this->diffusionRequest;

    $browse_query = DiffusionBrowseQuery::newFromDiffusionRequest($drequest);
    $results = $browse_query->loadPaths();

    $content = array();

    $content[] = $this->buildCrumbs(
      array(
        'branch' => true,
        'path'   => true,
        'view'   => 'browse',
      ));

    if (!$results) {

      if ($browse_query->getReasonForEmptyResultSet() ==
          DiffusionBrowseQuery::REASON_IS_FILE) {
        $controller = new DiffusionBrowseFileController($this->getRequest());
        $controller->setDiffusionRequest($drequest);
        return $this->delegateToController($controller);
      }

      $empty_result = new DiffusionEmptyResultView();
      $empty_result->setDiffusionRequest($drequest);
      $empty_result->setBrowseQuery($browse_query);
      $content[] = $empty_result;

    } else {

      $phids = array();
      foreach ($results as $result) {
        $data = $result->getLastCommitData();
        if ($data) {
          if ($data->getCommitDetail('authorPHID')) {
            $phids[$data->getCommitDetail('authorPHID')] = true;
          }
        }
      }
      $phids = array_keys($phids);

      $handles = id(new PhabricatorObjectHandleData($phids))->loadHandles();

      $browse_table = new DiffusionBrowseTableView();
      $browse_table->setDiffusionRequest($drequest);
      $browse_table->setHandles($handles);
      $browse_table->setPaths($results);

      $browse_panel = new AphrontPanelView();
      $browse_panel->appendChild($browse_table);

      $content[] = $browse_panel;
    }

    $content[] = $this->buildOpenRevisions();

    $nav = $this->buildSideNav('browse', false);
    $nav->appendChild($content);

    return $this->buildStandardPageResponse(
      $nav,
      array(
        'title' => basename($drequest->getPath()),
      ));
  }

}
