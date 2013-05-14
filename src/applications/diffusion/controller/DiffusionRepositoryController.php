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

    $browse_results = DiffusionBrowseResultSet::newFromConduit(
      $this->callConduitWithDiffusionRequest(
        'diffusion.browsequery',
        array(
          'path' => $drequest->getPath(),
          'commit' => $drequest->getCommit(),
          'renderReadme' => true,
        )));
    $browse_paths = $browse_results->getPaths();

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

    foreach ($browse_paths as $item) {
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
    $history_table->setUser($this->getRequest()->getUser());
    $history_table->setDiffusionRequest($drequest);
    $history_table->setHandles($handles);
    $history_table->setHistory($history);
    $history_table->loadRevisions();
    $history_table->setParents($history_query->getParents());
    $history_table->setIsHead(true);

    $callsign = $drequest->getRepository()->getCallsign();
    $all = phutil_tag(
      'a',
      array(
        'href' => $drequest->generateURI(
          array(
            'action' => 'history',
          )),
      ),
      pht('View Full Commit History'));

    $panel = new AphrontPanelView();
    $panel->setHeader(pht("Recent Commits &middot; %s", $all));
    $panel->appendChild($history_table);
    $panel->setNoBackground();

    $content[] = $panel;


    $browse_table = new DiffusionBrowseTableView();
    $browse_table->setDiffusionRequest($drequest);
    $browse_table->setHandles($handles);
    $browse_table->setPaths($browse_paths);
    $browse_table->setUser($this->getRequest()->getUser());

    $browse_panel = new AphrontPanelView();
    $browse_panel->setHeader(phutil_tag(
      'a',
      array('href' => $drequest->generateURI(array('action' => 'browse'))),
      pht('Browse Repository')));
    $browse_panel->appendChild($browse_table);
    $browse_panel->setNoBackground();

    $content[] = $browse_panel;

    $content[] = $this->buildTagListTable($drequest);

    $content[] = $this->buildBranchListTable($drequest);

    $readme = $browse_results->getReadmeContent();
    if ($readme) {
      $box = new PHUIBoxView();
      $box->setShadow(true);
      $box->appendChild($readme);
      $box->addPadding(PHUI::PADDING_LARGE);

      $panel = new AphrontPanelView();
      $panel->setHeader(pht('README'));
      $panel->setNoBackground();
      $panel->appendChild($box);
      $content[] = $panel;
    }

    return $this->buildApplicationPage(
      $content,
      array(
        'title' => $drequest->getRepository()->getName(),
        'dust' => true,
        'device' => true,
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
      $rows[] = array($key, $value);
    }

    $table = new AphrontTableView($rows);
    $table->setColumnClasses(
      array(
        'header',
        'wide',
      ));

    $panel = new AphrontPanelView();
    $panel->setHeader(pht('Repository Properties'));
    $panel->appendChild($table);
    $panel->setNoBackground();

    return $panel;
  }

  private function buildBranchListTable(DiffusionRequest $drequest) {
    if ($drequest->getBranch() !== null) {
      $limit = 15;

      $branches = DiffusionBranchInformation::newFromConduit(
        $this->callConduitWithDiffusionRequest(
          'diffusion.branchquery',
          array(
            'limit' => $limit
          )));
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
      $panel->setHeader(pht('Branches'));
      $panel->setNoBackground();

      if ($more_branches) {
        $panel->setCaption(pht('Showing %d branches.', $limit));
      }

      $panel->addButton(
        phutil_tag(
          'a',
          array(
            'href' => $drequest->generateURI(
              array(
                'action' => 'branches',
              )),
            'class' => 'grey button',
          ),
          pht("Show All Branches \xC2\xBB")));

      $panel->appendChild($table);

      return $panel;
    }

    return null;
  }

  private function buildTagListTable(DiffusionRequest $drequest) {
    $tag_limit = 15;
    $tags = array();
    try {
      $tags = DiffusionRepositoryTag::newFromConduit(
        $this->callConduitWithDiffusionRequest(
          'diffusion.tagsquery',
          array('limit' => $tag_limit + 1)));
    } catch (ConduitException $e) {
      if ($e->getMessage() != 'ERR-UNSUPPORTED-VCS') {
        throw $e;
      }
    }

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
    $panel->setHeader(pht('Tags'));

    if ($more_tags) {
      $panel->setCaption(pht('Showing the %d most recent tags.', $tag_limit));
    }

    $panel->addButton(
      phutil_tag(
        'a',
        array(
          'href' => $drequest->generateURI(
            array(
              'action' => 'tags',
            )),
          'class' => 'grey button',
        ),
        pht("Show All Tags \xC2\xBB")));
    $panel->appendChild($view);

    return $panel;
  }

}
