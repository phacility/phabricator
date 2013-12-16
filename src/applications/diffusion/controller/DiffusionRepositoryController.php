<?php

final class DiffusionRepositoryController extends DiffusionController {

  public function shouldAllowPublic() {
    return true;
  }

  public function processRequest() {
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();

    $content = array();

    $crumbs = $this->buildCrumbs();
    $content[] = $crumbs;

    $content[] = $this->buildPropertiesTable($drequest->getRepository());
    $phids = array();

    try {
      $history_results = $this->callConduitWithDiffusionRequest(
        'diffusion.historyquery',
        array(
          'commit' => $drequest->getCommit(),
          'path' => $drequest->getPath(),
          'offset' => 0,
          'limit' => 15));
      $history = DiffusionPathChange::newFromConduit(
        $history_results['pathChanges']);

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
      $history_exception = null;
    } catch (Exception $ex) {
      $history_results = null;
      $history = null;
      $history_exception = $ex;
    }

    try {
      $browse_results = DiffusionBrowseResultSet::newFromConduit(
        $this->callConduitWithDiffusionRequest(
          'diffusion.browsequery',
          array(
            'path' => $drequest->getPath(),
            'commit' => $drequest->getCommit(),
          )));
      $browse_paths = $browse_results->getPaths();

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

      $browse_exception = null;
    } catch (Exception $ex) {
      $browse_results = null;
      $browse_paths = null;
      $browse_exception = $ex;
    }

    $phids = array_keys($phids);
    $handles = $this->loadViewerHandles($phids);

    if ($browse_results) {
      $readme = $this->callConduitWithDiffusionRequest(
        'diffusion.readmequery',
        array(
         'paths' => $browse_results->getPathDicts()
        ));
    } else {
      $readme = null;
    }

    $content[] = $this->buildHistoryTable(
      $history_results,
      $history,
      $history_exception,
      $handles);

    $content[] = $this->buildBrowseTable(
      $browse_results,
      $browse_paths,
      $browse_exception,
      $handles);

    try {
      $content[] = $this->buildTagListTable($drequest);
    } catch (Exception $ex) {
      if (!$repository->isImporting()) {
        $content[] = $this->renderStatusMessage(
          pht('Unable to Load Tags'),
          $ex->getMessage());
      }
    }

    try {
      $content[] = $this->buildBranchListTable($drequest);
    } catch (Exception $ex) {
      if (!$repository->isImporting()) {
        $content[] = $this->renderStatusMessage(
          pht('Unable to Load Branches'),
          $ex->getMessage());
      }
    }

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

    if (!$repository->isTracked()) {
      $header->setStatus('policy-noone', '', pht('Inactive'));
    } else if ($repository->isImporting()) {
      $header->setStatus('time', 'red', pht('Importing...'));
    } else {
      $header->setStatus('oh-ok', '', pht('Active'));
    }


    $actions = $this->buildActionList($repository);

    $view = id(new PHUIPropertyListView())
      ->setUser($user);
    $view->addProperty(pht('Callsign'), $repository->getCallsign());

    if ($repository->isHosted()) {
      $serve_off = PhabricatorRepository::SERVE_OFF;
      $callsign = $repository->getCallsign();
      $repo_path = '/diffusion/'.$callsign.'/';

      $serve_ssh = $repository->getServeOverSSH();
      if ($serve_ssh !== $serve_off) {
        $uri = new PhutilURI(PhabricatorEnv::getProductionURI($repo_path));

        if ($repository->isSVN()) {
          $uri->setProtocol('svn+ssh');
        } else {
          $uri->setProtocol('ssh');
        }

        $ssh_user = PhabricatorEnv::getEnvConfig('diffusion.ssh-user');
        if ($ssh_user) {
          $uri->setUser($ssh_user);
        }

        $uri->setPort(PhabricatorEnv::getEnvConfig('diffusion.ssh-port'));

        $clone_uri = $this->renderCloneURI(
          $uri,
          $serve_ssh,
          '/settings/panel/ssh/');

        $view->addProperty(pht('Clone URI (SSH)'), $clone_uri);
      }

      $serve_http = $repository->getServeOverHTTP();
      if ($serve_http !== $serve_off) {
        $http_uri = PhabricatorEnv::getProductionURI($repo_path);

        $clone_uri = $this->renderCloneURI(
          $http_uri,
          $serve_http,
          PhabricatorEnv::getEnvConfig('diffusion.allow-http-auth')
            ? '/settings/panel/vcspassword/'
            : null);

        $view->addProperty(pht('Clone URI (HTTP)'), $clone_uri);
      }
    } else {
      switch ($repository->getVersionControlSystem()) {
        case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
        case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
          $view->addProperty(
            pht('Clone URI'),
            $this->renderCloneURI(
              $repository->getPublicRemoteURI()));
          break;
        case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
          $view->addProperty(
            pht('Repository Root'),
            $this->renderCloneURI(
              $repository->getPublicRemoteURI()));
          break;
      }
    }

    $description = $repository->getDetail('description');
    if (strlen($description)) {
      $description = PhabricatorMarkupEngine::renderOneObject(
        $repository,
        'description',
        $user);
      $view->addSectionHeader(pht('Description'));
      $view->addTextContent($description);
    }

    $view->setActionList($actions);

    return id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($view);

  }

  private function buildBranchListTable(DiffusionRequest $drequest) {
    $viewer = $this->getRequest()->getUser();

    if ($drequest->getBranch() === null) {
      return null;
    }

    $limit = 15;

    $branches = DiffusionBranchInformation::newFromConduit(
      $this->callConduitWithDiffusionRequest(
        'diffusion.branchquery',
        array(
          'limit' => $limit + 1,
        )));
    if (!$branches) {
      return null;
    }

    $more_branches = (count($branches) > $limit);
    $branches = array_slice($branches, 0, $limit);

    $commits = id(new DiffusionCommitQuery())
      ->setViewer($viewer)
      ->withIdentifiers(mpull($branches, 'getHeadCommitIdentifier'))
      ->withRepository($drequest->getRepository())
      ->execute();

    $table = id(new DiffusionBranchTableView())
      ->setUser($viewer)
      ->setDiffusionRequest($drequest)
      ->setBranches($branches)
      ->setCommits($commits);

    $panel = id(new AphrontPanelView())
      ->setHeader(pht('Branches'))
      ->setNoBackground();

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

  private function buildTagListTable(DiffusionRequest $drequest) {
    $viewer = $this->getRequest()->getUser();

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

    $commits = id(new DiffusionCommitQuery())
      ->setViewer($viewer)
      ->withIdentifiers(mpull($tags, 'getCommitIdentifier'))
      ->withRepository($drequest->getRepository())
      ->needCommitData(true)
      ->execute();

    $view = id(new DiffusionTagListView())
      ->setUser($viewer)
      ->setDiffusionRequest($drequest)
      ->setTags($tags)
      ->setCommits($commits);

    $phids = $view->getRequiredHandlePHIDs();
    $handles = $this->loadViewerHandles($phids);
    $view->setHandles($handles);

    $panel = id(new AphrontPanelView())
      ->setHeader(pht('Tags'))
      ->setNoBackground(true);

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

    if ($repository->isHosted()) {
      $callsign = $repository->getCallsign();
      $push_uri = $this->getApplicationURI(
        'pushlog/?repositories=r'.$callsign);

      $view->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('View Push Logs'))
          ->setIcon('transcript')
          ->setHref($push_uri));
    }

    return $view;
  }

  private function buildHistoryTable(
    $history_results,
    $history,
    $history_exception,
    array $handles) {

    $request = $this->getRequest();
    $viewer = $request->getUser();
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();

    if ($history_exception) {
      if ($repository->isImporting()) {
        return $this->renderStatusMessage(
          pht('Still Importing...'),
          pht(
            'This repository is still importing. History is not yet '.
            'available.'));
      } else {
        return $this->renderStatusMessage(
          pht('Unable to Retrieve History'),
          $history_exception->getMessage());
      }
    }

    $history_table = id(new DiffusionHistoryTableView())
      ->setUser($viewer)
      ->setDiffusionRequest($drequest)
      ->setHandles($handles)
      ->setHistory($history);

    // TODO: Super sketchy.
    $history_table->loadRevisions();

    if ($history_results) {
      $history_table->setParents($history_results['parents']);
    }

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

    return $panel;
  }

  private function buildBrowseTable(
    $browse_results,
    $browse_paths,
    $browse_exception,
    array $handles) {

    $request = $this->getRequest();
    $viewer = $request->getUser();
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();

    if ($browse_exception) {
      if ($repository->isImporting()) {
        // The history table renders a useful message.
        return null;
      } else {
        return $this->renderStatusMessage(
          pht('Unable to Retrieve Paths'),
          $browse_exception->getMessage());
      }
    }

    $browse_table = id(new DiffusionBrowseTableView())
      ->setUser($viewer)
      ->setDiffusionRequest($drequest)
      ->setHandles($handles);
    if ($browse_paths) {
      $browse_table->setPaths($browse_paths);
    } else {
      $browse_table->setPaths(array());
    }

    $browse_uri = $drequest->generateURI(array('action' => 'browse'));

    $browse_panel = new AphrontPanelView();
    $browse_panel->setHeader(
      phutil_tag(
        'a',
        array('href' => $browse_uri),
        pht('Browse Repository')));
    $browse_panel->appendChild($browse_table);
    $browse_panel->setNoBackground();

    return $browse_panel;
  }

  private function renderCloneURI(
    $uri,
    $serve_mode = null,
    $manage_uri = null) {

    require_celerity_resource('diffusion-icons-css');

    Javelin::initBehavior('select-on-click');

    $input = javelin_tag(
      'input',
      array(
        'type' => 'text',
        'value' => (string)$uri,
        'class' => 'diffusion-clone-uri',
        'sigil' => 'select-on-click',
      ));

    $extras = array();
    if ($serve_mode) {
      if ($serve_mode === PhabricatorRepository::SERVE_READONLY) {
        $extras[] = pht('(Read Only)');
      }
    }

    if ($manage_uri) {
      if ($this->getRequest()->getUser()->isLoggedIn()) {
        $extras[] = phutil_tag(
          'a',
          array(
            'href' => $manage_uri,
          ),
          pht('Manage Credentials'));
      }
    }

    if ($extras) {
      $extras = phutil_implode_html(' ', $extras);
      $extras = phutil_tag(
        'div',
        array(
          'class' => 'diffusion-clone-extras',
        ),
        $extras);
    }

    return array($input, $extras);
  }

}
