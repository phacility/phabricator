<?php

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
        if ($data->getCommitDetail('committerPHID')) {
          $phids[$data->getCommitDetail('committerPHID')] = true;
        }
      }
    }

    foreach ($browse_results as $item) {
      $data = $item->getLastCommitData();
      if ($data) {
        if ($data->getCommitDetail('authorPHID')) {
          $phids[$data->getCommitDetail('authorPHID')] = true;
        }
        if ($data->getCommitDetail('committerPHID')) {
          $phids[$data->getCommitDetail('committerPHID')] = true;
        }
      }
    }

    $phids = array_keys($phids);
    $handles = $this->loadViewerHandles($phids);

    $history_table = new DiffusionHistoryTableView();
    $history_table->setDiffusionRequest($drequest);
    $history_table->setHandles($handles);
    $history_table->setHistory($history);
    $history_table->loadRevisions();
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
    $browse_table->setUser($this->getRequest()->getUser());

    $browse_panel = new AphrontPanelView();
    $browse_panel->setHeader('Browse Repository');
    $browse_panel->appendChild($browse_table);

    $content[] = $browse_panel;

    $content[] = $this->buildTagListTable($drequest);

    $content[] = $this->buildBranchListTable($drequest);

    $readme = $browse_query->renderReadme($browse_results);
    if ($readme) {
      $panel = new AphrontPanelView();
      $panel->setHeader('README');
      $panel->appendChild($readme);
      $content[] = $panel;
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

  private function buildBranchListTable(DiffusionRequest $drequest) {
    if ($drequest->getBranch() !== null) {
      $limit = 15;

      $branch_query = DiffusionBranchQuery::newFromDiffusionRequest($drequest);
      $branch_query->setLimit($limit + 1);
      $branches = $branch_query->loadBranches();

      if (!$branches) {
          return null;
      }

      $more_branches = (count($branches) > $limit);
      $branches = array_slice($branches, 0, $limit);

      $commits = id(new PhabricatorAuditCommitQuery())
        ->withIdentifiers(
          $drequest->getRepository()->getID(),
          mpull($branches, 'getHeadCommitIdentifier'))
        ->needCommitData(true)
        ->execute();

      $table = new DiffusionBranchTableView();
      $table->setDiffusionRequest($drequest);
      $table->setBranches($branches);
      $table->setCommits($commits);
      $table->setUser($this->getRequest()->getUser());

      $panel = new AphrontPanelView();
      $panel->setHeader('Branches');

      if ($more_branches) {
        $panel->setCaption('Showing ' . $limit . ' branches.');
      }

      $panel->addButton(
        phutil_render_tag(
          'a',
          array(
            'href' => $drequest->generateURI(
              array(
                'action' => 'branches',
              )),
            'class' => 'grey button',
          ),
          "Show All Branches \xC2\xBB"));

      $panel->appendChild($table);

      return $panel;
    }

    return null;
  }

  private function buildTagListTable(DiffusionRequest $drequest) {
    $tag_limit = 15;

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
    $handles = $this->loadViewerHandles($phids);
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
