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

final class DiffusionRepositoryController extends DiffusionController {

  public function processRequest() {
    $drequest = $this->diffusionRequest;

    $content = array();

    $crumbs = $this->buildCrumbs();
    $content[] = $crumbs;

    $content[] = $this->buildPropertiesTable($drequest->getRepository());

    $history_query = DiffusionHistoryQuery::newFromDiffusionRequest(
      $drequest);
    $history_query->setLimit(15);
    $history_query->needParents(true);
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
    $history_table->setParents($history_query->getParents());
    $history_table->setIsHead(true);

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

    $content[] = $this->buildTagListTable($drequest);

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

  private function buildPropertiesTable(PhabricatorRepository $repository) {

    $properties = array();
    $properties['Name'] = $repository->getName();
    $properties['Callsign'] = $repository->getCallsign();
    $properties['Description'] = $repository->getDetail('description');
    switch ($repository->getVersionControlSystem()) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
      case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
        $properties['Clone URI'] = $repository->getPublicRemoteURI();
        break;
      case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
        $properties['Repository Root'] = $repository->getPublicRemoteURI();
        break;
    }

    $rows = array();
    foreach ($properties as $key => $value) {
      $rows[] = array(
        phutil_escape_html($key),
        phutil_escape_html($value));
    }

    $table = new AphrontTableView($rows);
    $table->setColumnClasses(
      array(
        'header',
        'wide',
      ));

    $panel = new AphrontPanelView();
    $panel->setHeader('Repository Properties');
    $panel->appendChild($table);

    return $panel;
  }

  private function buildTagListTable(DiffusionRequest $drequest) {
    $tag_limit = 25;

    $query = DiffusionTagListQuery::newFromDiffusionRequest($drequest);
    $query->setLimit($tag_limit + 1);
    $tags = $query->loadTags();

    if (!$tags) {
      return null;
    }

    $more_tags = (count($tags) > $tag_limit);
    $tags = array_slice($tags, 0, $tag_limit);

    $commits = id(new PhabricatorAuditCommitQuery())
      ->withIdentifiers(
        $drequest->getRepository()->getID(),
        mpull($tags, 'getCommitIdentifier'))
      ->needCommitData(true)
      ->execute();

    $view = new DiffusionTagListView();
    $view->setDiffusionRequest($drequest);
    $view->setTags($tags);
    $view->setUser($this->getRequest()->getUser());
    $view->setCommits($commits);

    $phids = $view->getRequiredHandlePHIDs();
    $handles = id(new PhabricatorObjectHandleData($phids))->loadHandles();
    $view->setHandles($handles);

    $panel = new AphrontPanelView();
    $panel->setHeader('Tags');

    if ($more_tags) {
      $panel->setCaption('Showing the '.$tag_limit.' most recent tags.');
    }

    $panel->addButton(
      phutil_render_tag(
        'a',
        array(
          'href' => $drequest->generateURI(
            array(
              'action' => 'tags',
            )),
          'class' => 'grey button',
        ),
        "Show All Tags \xC2\xBB"));
    $panel->appendChild($view);

    return $panel;
  }

}
