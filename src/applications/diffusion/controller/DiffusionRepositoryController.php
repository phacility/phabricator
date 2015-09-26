<?php

final class DiffusionRepositoryController extends DiffusionController {

  public function shouldAllowPublic() {
    return true;
  }

  protected function processDiffusionRequest(AphrontRequest $request) {
    $viewer = $request->getUser();

    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();

    $content = array();

    $crumbs = $this->buildCrumbs();
    $content[] = $crumbs;

    $content[] = $this->buildPropertiesTable($drequest->getRepository());

    // Before we do any work, make sure we're looking at a some content: we're
    // on a valid branch, and the repository is not empty.
    $page_has_content = false;
    $empty_title = null;
    $empty_message = null;

    // If this VCS supports branches, check that the selected branch actually
    // exists.
    if ($drequest->supportsBranches()) {
      // NOTE: Mercurial may have multiple branch heads with the same name.
      $ref_cursors = id(new PhabricatorRepositoryRefCursorQuery())
        ->setViewer($viewer)
        ->withRepositoryPHIDs(array($repository->getPHID()))
        ->withRefTypes(array(PhabricatorRepositoryRefCursor::TYPE_BRANCH))
        ->withRefNames(array($drequest->getBranch()))
        ->execute();
      if ($ref_cursors) {
        // This is a valid branch, so we necessarily have some content.
        $page_has_content = true;
      } else {
        $empty_title = pht('No Such Branch');
        $empty_message = pht(
          'There is no branch named "%s" in this repository.',
          $drequest->getBranch());
      }
    }

    // If we didn't find any branches, check if there are any commits at all.
    // This can tailor the message for empty repositories.
    if (!$page_has_content) {
      $any_commit = id(new DiffusionCommitQuery())
        ->setViewer($viewer)
        ->withRepository($repository)
        ->setLimit(1)
        ->execute();
      if ($any_commit) {
        if (!$drequest->supportsBranches()) {
          $page_has_content = true;
        }
      } else {
        $empty_title = pht('Empty Repository');
        $empty_message = pht('This repository does not have any commits yet.');
      }
    }

    if ($page_has_content) {
      $content[] = $this->buildNormalContent($drequest);
    } else {
      $content[] = id(new PHUIInfoView())
        ->setTitle($empty_title)
        ->setSeverity(PHUIInfoView::SEVERITY_WARNING)
        ->setErrors(array($empty_message));
    }

    return $this->buildApplicationPage(
      $content,
      array(
        'title' => $drequest->getRepository()->getName(),
      ));
  }


  private function buildNormalContent(DiffusionRequest $drequest) {
    $repository = $drequest->getRepository();

    $phids = array();
    $content = array();

    try {
      $history_results = $this->callConduitWithDiffusionRequest(
        'diffusion.historyquery',
        array(
          'commit' => $drequest->getCommit(),
          'path' => $drequest->getPath(),
          'offset' => 0,
          'limit' => 15,
        ));
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

    $readme = null;
    if ($browse_results) {
      $readme_path = $browse_results->getReadmePath();
      if ($readme_path) {
        $readme_content = $this->callConduitWithDiffusionRequest(
          'diffusion.filecontentquery',
          array(
            'path' => $readme_path,
            'commit' => $drequest->getStableCommit(),
          ));
        if ($readme_content) {
          $readme = id(new DiffusionReadmeView())
            ->setUser($this->getViewer())
            ->setPath($readme_path)
            ->setContent($readme_content['corpus']);
        }
      }
    }

    $content[] = $this->buildBrowseTable(
      $browse_results,
      $browse_paths,
      $browse_exception,
      $handles);

    $content[] = $this->buildHistoryTable(
      $history_results,
      $history,
      $history_exception);

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
      $content[] = $readme;
    }

    return $content;
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
      ->setObject($repository)
      ->setUser($user);

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

    $view->invokeWillRenderEvent();

    $description = $repository->getDetail('description');
    if (strlen($description)) {
      $description = PhabricatorMarkupEngine::renderOneObject(
        $repository,
        'description',
        $user);
      $view->addSectionHeader(
        pht('Description'), PHUIPropertyListView::ICON_SUMMARY);
      $view->addTextContent($description);
    }

    $view->setActionList($actions);

    $box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($view);

    $info = null;
    $drequest = $this->getDiffusionRequest();

    // Try to load alternatives. This may fail for repositories which have not
    // cloned yet. If it does, just ignore it and continue.
    try {
      $alternatives = $drequest->getRefAlternatives();
    } catch (ConduitClientException $ex) {
      $alternatives = array();
    }

    if ($alternatives) {
      $message = array(
        pht(
          'The ref "%s" is ambiguous in this repository.',
          $drequest->getBranch()),
        ' ',
        phutil_tag(
          'a',
          array(
            'href' => $drequest->generateURI(
              array(
                'action' => 'refs',
              )),
          ),
          pht('View Alternatives')),
      );

      $messages = array($message);

      $info = id(new PHUIInfoView())
        ->setSeverity(PHUIInfoView::SEVERITY_WARNING)
        ->setErrors(array($message));

      $box->setInfoView($info);
    }


    return $box;
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
        'closed' => false,
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
      ->setIconFont('fa-code-fork');

    $button = new PHUIButtonView();
    $button->setText(pht('Show All Branches'));
    $button->setTag('a');
    $button->setIcon($icon);
    $button->setHref($drequest->generateURI(
      array(
        'action' => 'branches',
      )));

    $header->addActionLink($button);
    $panel->setHeader($header);
    $panel->setTable($table);

    return $panel;
  }

  private function buildTagListTable(DiffusionRequest $drequest) {
    $viewer = $this->getRequest()->getUser();
    $repository = $drequest->getRepository();

    switch ($repository->getVersionControlSystem()) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
        // no tags in SVN
        return null;
    }
    $tag_limit = 15;
    $tags = array();
    $tags = DiffusionRepositoryTag::newFromConduit(
      $this->callConduitWithDiffusionRequest(
        'diffusion.tagsquery',
        array(
          // On the home page, we want to find tags on any branch.
          'commit' => null,
          'limit' => $tag_limit + 1,
        )));

    if (!$tags) {
      return null;
    }

    $more_tags = (count($tags) > $tag_limit);
    $tags = array_slice($tags, 0, $tag_limit);

    $commits = id(new DiffusionCommitQuery())
      ->setViewer($viewer)
      ->withIdentifiers(mpull($tags, 'getCommitIdentifier'))
      ->withRepository($repository)
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
    $button->setText(pht('Show All Tags'));
    $button->setTag('a');
    $button->setIcon($icon);
    $button->setHref($drequest->generateURI(
      array(
        'action' => 'tags',
      )));

    $header->addActionLink($button);

    $panel->setHeader($header);
    $panel->setTable($view);

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
    $history_exception) {

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
    $panel->setTable($history_table);

    return $panel;
  }

  private function buildBrowseTable(
    $browse_results,
    $browse_paths,
    $browse_exception,
    array $handles) {

    require_celerity_resource('diffusion-icons-css');

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

    $locate_panel = null;
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
      $form_box = id(new PHUIBoxView())
        ->appendChild($form->buildLayoutView());
      $locate_panel = id(new PHUIObjectBoxView())
        ->setHeaderText('Locate File')
        ->appendChild($form_box);
    }

    $browse_panel->setTable($browse_table);

    return array($locate_panel, $browse_panel);
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
