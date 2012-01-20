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

class DiffusionRepositoryController extends DiffusionController {

  public function processRequest() {
    $drequest = $this->diffusionRequest;

    $content = array();

    $crumbs = $this->buildCrumbs();
    $content[] = $crumbs;

    $history_query = DiffusionHistoryQuery::newFromDiffusionRequest(
      $drequest);
    $history_query->setLimit(15);
    $history = $history_query->loadHistory();

    $browse_query = DiffusionBrowseQuery::newFromDiffusionRequest($drequest);
    $browse_results = $browse_query->loadPaths();

    $phids = array();

    foreach ($history as $item) {
      $data = $item->getCommitData();
      if ($data) {
        if ($data->getCommitDetail('authorPHID')) {
          $phids[$data->getCommitDetail('authorPHID')] = true;
        }
      }
    }

    foreach ($browse_results as $item) {
      $data = $item->getLastCommitData();
      if ($data) {
        if ($data->getCommitDetail('authorPHID')) {
          $phids[$data->getCommitDetail('authorPHID')] = true;
        }
      }
    }

    $phids = array_keys($phids);
    $handles = id(new PhabricatorObjectHandleData($phids))->loadHandles();

    $history_table = new DiffusionHistoryTableView();
    $history_table->setDiffusionRequest($drequest);
    $history_table->setHandles($handles);
    $history_table->setHistory($history);

    $callsign = $drequest->getRepository()->getCallsign();
    $all = phutil_render_tag(
      'a',
      array(
        'href' => "/diffusion/{$callsign}/history/",
      ),
      'View Full Commit History');

    $panel = new AphrontPanelView();
    $panel->setHeader("Recent Commits &middot; {$all}");
    $panel->appendChild($history_table);

    $content[] = $panel;


    $browse_table = new DiffusionBrowseTableView();
    $browse_table->setDiffusionRequest($drequest);
    $browse_table->setHandles($handles);
    $browse_table->setPaths($browse_results);

    $browse_panel = new AphrontPanelView();
    $browse_panel->setHeader('Browse Repository');
    $browse_panel->appendChild($browse_table);

    $content[] = $browse_panel;

    if ($drequest->getBranch() !== null) {
      $branch_query = DiffusionBranchQuery::newFromDiffusionRequest($drequest);
      $branches = $branch_query->loadBranches();

      $branch_table = new DiffusionBranchTableView();
      $branch_table->setDiffusionRequest($drequest);
      $branch_table->setBranches($branches);

      $branch_panel = new AphrontPanelView();
      $branch_panel->setHeader('Branches');
      $branch_panel->appendChild($branch_table);

      $content[] = $branch_panel;
    }

    return $this->buildStandardPageResponse(
      $content,
      array(
        'title' => $drequest->getRepository()->getName(),
      ));
  }

}
