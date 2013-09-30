<?php

final class DiffusionRepositoryController extends DiffusionController {

  public function shouldAllowPublic() {
    return true;
  }

  public function processRequest() {
    $drequest = $this->diffusionRequest;

    $content = array();

    $crumbs = $this->buildCrumbs();
    $content[] = $crumbs;

    $content[] = $this->buildPropertiesTable($drequest->getRepository());

    $history_results = $this->callConduitWithDiffusionRequest(
      'diffusion.historyquery',
      array(
        'commit' => $drequest->getCommit(),
        'path' => $drequest->getPath(),
        'offset' => 0,
        'limit' => 15));
    $history = DiffusionPathChange::newFromConduit(
      $history_results['pathChanges']);

    $browse_results = DiffusionBrowseResultSet::newFromConduit(
      $this->callConduitWithDiffusionRequest(
        'diffusion.browsequery',
        array(
          'path' => $drequest->getPath(),
          'commit' => $drequest->getCommit(),
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

    $readme = $this->callConduitWithDiffusionRequest(
      'diffusion.readmequery',
      array(
       'paths' => $browse_results->getPathDicts()
        ));

    $history_table = new DiffusionHistoryTableView();
    $history_table->setUser($this->getRequest()->getUser());
    $history_table->setDiffusionRequest($drequest);
    $history_table->setHandles($handles);
    $history_table->setHistory($history);
    $history_table->loadRevisions();
    $history_table->setParents($history_results['parents']);
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
        'device' => true,
      ));
  }

  private function buildPropertiesTable(PhabricatorRepository $repository) {
    $user = $this->getRequest()->getUser();

    $header = id(new PHUIHeaderView())
      ->setHeader($repository->getName())
      ->setUser($user)
      ->setPolicyObject($repository);

    $actions = $this->buildActionList($repository);

    $view = id(new PhabricatorPropertyListView())
      ->setUser($user);
    $view->addProperty(pht('Callsign'), $repository->getCallsign());

    switch ($repository->getVersionControlSystem()) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
      case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
        $view->addProperty(
          pht('Clone URI'),
          $repository->getPublicRemoteURI());
        break;
      case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
        $view->addProperty(
          pht('Repository Root'),
          $repository->getPublicRemoteURI());
        break;
    }

    $description = $repository->getDetail('description');
    if (strlen($description)) {
      $description = PhabricatorMarkupEngine::renderOneObject(
        $repository,
        'description',
        $user);
      $view->addTextContent($description);
    }

    return id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->setActionList($actions)
      ->setPropertyList($view);

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
          array(
            // On the home page, we want to find tags on any branch.
            'commit' => null,
            'limit' => $tag_limit + 1,
          )));
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
    $panel->setNoBackground(true);

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

  private function buildActionList(PhabricatorRepository $repository) {
    $viewer = $this->getRequest()->getUser();

    $view_uri = $this->getApplicationURI($repository->getCallsign().'/');
    $edit_uri = $this->getApplicationURI($repository->getCallsign().'/edit/');

    $view = id(new PhabricatorActionListView())
      ->setUser($viewer)
      ->setObject($repository)
      ->setObjectURI($view_uri);

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $repository,
      PhabricatorPolicyCapability::CAN_EDIT);

    $view->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Repository'))
        ->setIcon('edit')
        ->setHref($edit_uri)
        ->setWorkflow(!$can_edit)
        ->setDisabled(!$can_edit));

    return $view;
  }

}
