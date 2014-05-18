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
         'paths' => $browse_results->getPathDicts(),
         'commit' => $drequest->getStableCommit(),
        ));
    } else {
      $readme = null;
    }

    $content[] = $this->buildBrowseTable(
      $browse_results,
      $browse_paths,
      $browse_exception,
      $handles);

    $content[] = $this->buildHistoryTable(
      $history_results,
      $history,
      $history_exception,
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
      $box->appendChild($readme);
      $box->addPadding(PHUI::PADDING_LARGE);

      $panel = new PHUIObjectBoxView();
      $panel->setHeaderText(pht('README'));
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
      $header->setStatus('fa-ban', 'dark', pht('Inactive'));
    } else if ($repository->isImporting()) {
      $header->setStatus('fa-clock-o', 'indigo', pht('Importing...'));
    } else {
      $header->setStatus('fa-check', 'bluegrey', pht('Active'));
    }


    $actions = $this->buildActionList($repository);

    $view = id(new PHUIPropertyListView())
      ->setUser($user);

    $project_phids = PhabricatorEdgeQuery::loadDestinationPHIDs(
      $repository->getPHID(),
      PhabricatorEdgeConfig::TYPE_OBJECT_HAS_PROJECT);
    if ($project_phids) {
      $this->loadHandles($project_phids);
      $view->addProperty(
        pht('Projects'),
        $this->renderHandlesForPHIDs($project_phids));
    }

    if ($repository->isHosted()) {
      $ssh_uri = $repository->getSSHCloneURIObject();
      if ($ssh_uri) {
        $clone_uri = $this->renderCloneCommand(
          $repository,
          $ssh_uri,
          $repository->getServeOverSSH(),
          '/settings/panel/ssh/');

        $view->addProperty(
          $repository->isSVN()
            ? pht('Checkout (SSH)')
            : pht('Clone (SSH)'),
          $clone_uri);
      }

      $http_uri = $repository->getHTTPCloneURIObject();
      if ($http_uri) {
        $clone_uri = $this->renderCloneCommand(
          $repository,
          $http_uri,
          $repository->getServeOverHTTP(),
          PhabricatorEnv::getEnvConfig('diffusion.allow-http-auth')
            ? '/settings/panel/vcspassword/'
            : null);

        $view->addProperty(
          $repository->isSVN()
            ? pht('Checkout (HTTP)')
            : pht('Clone (HTTP)'),
          $clone_uri);
      }
    } else {
      switch ($repository->getVersionControlSystem()) {
        case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
        case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
          $view->addProperty(
            pht('Clone'),
            $this->renderCloneCommand(
              $repository,
              $repository->getPublicCloneURI()));
          break;
        case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
          $view->addProperty(
            pht('Checkout'),
            $this->renderCloneCommand(
              $repository,
              $repository->getPublicCloneURI()));
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

    $branches = $this->callConduitWithDiffusionRequest(
      'diffusion.branchquery',
      array(
        'limit' => $limit + 1,
      ));
    if (!$branches) {
      return null;
    }

    $more_branches = (count($branches) > $limit);
    $branches = array_slice($branches, 0, $limit);

    $branches = DiffusionRepositoryRef::loadAllFromDictionaries($branches);

    $commits = id(new DiffusionCommitQuery())
      ->setViewer($viewer)
      ->withIdentifiers(mpull($branches, 'getCommitIdentifier'))
      ->withRepository($drequest->getRepository())
      ->execute();

    $table = id(new DiffusionBranchTableView())
      ->setUser($viewer)
      ->setDiffusionRequest($drequest)
      ->setBranches($branches)
      ->setCommits($commits);

    $panel = new PHUIObjectBoxView();
    $header = new PHUIHeaderView();
    $header->setHeader(pht('Branches'));

    if ($more_branches) {
      $header->setSubHeader(pht('Showing %d branches.', $limit));
    }

    $icon = id(new PHUIIconView())
      ->setIconFont('fa-fork');

    $button = new PHUIButtonView();
    $button->setText(pht("Show All Branches"));
    $button->setTag('a');
    $button->setIcon($icon);
    $button->setHref($drequest->generateURI(
            array(
              'action' => 'branches',
            )));

    $header->addActionLink($button);
    $panel->setHeader($header);
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

    $panel = new PHUIObjectBoxView();
    $header = new PHUIHeaderView();
    $header->setHeader(pht('Tags'));

    if ($more_tags) {
      $header->setSubHeader(
        pht('Showing the %d most recent tags.', $tag_limit));
    }

    $icon = id(new PHUIIconView())
      ->setIconFont('fa-tag');

    $button = new PHUIButtonView();
    $button->setText(pht("Show All Tags"));
    $button->setTag('a');
    $button->setIcon($icon);
    $button->setHref($drequest->generateURI(
      array(
        'action' => 'tags',
      )));

    $header->addActionLink($button);

    $panel->setHeader($header);
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
        ->setIcon('fa-pencil')
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
          ->setIcon('fa-list-alt')
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

    $icon = id(new PHUIIconView())
      ->setIconFont('fa-list-alt');

    $button = id(new PHUIButtonView())
      ->setText(pht('View Full History'))
      ->setHref($drequest->generateURI(
        array(
          'action' => 'history',
        )))
      ->setTag('a')
      ->setIcon($icon);

    $panel = new PHUIObjectBoxView();
    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Recent Commits'))
      ->addActionLink($button);
    $panel->setHeader($header);
    $panel->appendChild($history_table);

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

    $browse_panel = new PHUIObjectBoxView();
    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Repository'));

    $icon = id(new PHUIIconView())
      ->setIconFont('fa-folder-open');

    $button = new PHUIButtonView();
    $button->setText(pht('Browse Repository'));
    $button->setTag('a');
    $button->setIcon($icon);
    $button->setHref($browse_uri);

    $header->addActionLink($button);
    $browse_panel->setHeader($header);

    if ($repository->canUsePathTree()) {
      Javelin::initBehavior(
        'diffusion-locate-file',
        array(
          'controlID' => 'locate-control',
          'inputID' => 'locate-input',
          'browseBaseURI' => (string)$drequest->generateURI(
            array(
              'action' => 'browse',
            )),
          'uri' => (string)$drequest->generateURI(
            array(
              'action' => 'pathtree',
            )),
        ));

      $form = id(new AphrontFormView())
        ->setUser($viewer)
        ->appendChild(
          id(new AphrontFormTypeaheadControl())
            ->setHardpointID('locate-control')
            ->setID('locate-input')
            ->setLabel(pht('Locate File')));
      $browse_panel->appendChild($form->buildLayoutView());
    }

    $browse_panel->appendChild($browse_table);

    return $browse_panel;
  }

  private function renderCloneCommand(
    PhabricatorRepository $repository,
    $uri,
    $serve_mode = null,
    $manage_uri = null) {

    require_celerity_resource('diffusion-icons-css');

    Javelin::initBehavior('select-on-click');

    switch ($repository->getVersionControlSystem()) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
        $command = csprintf(
          'git clone %R',
          $uri);
        break;
      case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
        $command = csprintf(
          'hg clone %R',
          $uri);
        break;
      case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
        if ($repository->isHosted()) {
          $command = csprintf(
            'svn checkout %R %R',
            $uri,
            $repository->getCloneName());
        } else {
          $command = csprintf(
            'svn checkout %R',
            $uri);
        }
        break;
    }

    $input = javelin_tag(
      'input',
      array(
        'type' => 'text',
        'value' => (string)$command,
        'class' => 'diffusion-clone-uri',
        'sigil' => 'select-on-click',
        'readonly' => 'true',
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
