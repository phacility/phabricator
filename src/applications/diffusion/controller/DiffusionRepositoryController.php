<?php

final class DiffusionRepositoryController extends DiffusionController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $response = $this->loadDiffusionContext();
    if ($response) {
      return $response;
    }

    $viewer = $this->getViewer();
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();

    $crumbs = $this->buildCrumbs();
    $crumbs->setBorder(true);

    $header = $this->buildHeaderView($repository);
    $curtain = $this->buildCurtain($repository);
    $property_table = $this->buildPropertiesTable($repository);
    $description = $this->buildDescriptionView($repository);
    $locate_file = $this->buildLocateFile();

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
      $content = $this->buildNormalContent($drequest);
    } else {
      $content = id(new PHUIInfoView())
        ->setTitle($empty_title)
        ->setSeverity(PHUIInfoView::SEVERITY_WARNING)
        ->setErrors(array($empty_message));
    }

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setCurtain($curtain)
      ->setMainColumn(array(
        $property_table,
        $description,
        $locate_file,
      ))
      ->setFooter($content);

    return $this->newPage()
      ->setTitle(
        array(
          $repository->getName(),
          $repository->getDisplayName(),
        ))
      ->setCrumbs($crumbs)
      ->appendChild(array(
        $view,
      ));
  }


  private function buildNormalContent(DiffusionRequest $drequest) {
    $request = $this->getRequest();
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

    $browse_pager = id(new PHUIPagerView())
      ->readFromRequest($request);

    try {
      $browse_results = DiffusionBrowseResultSet::newFromConduit(
        $this->callConduitWithDiffusionRequest(
          'diffusion.browsequery',
          array(
            'path' => $drequest->getPath(),
            'commit' => $drequest->getCommit(),
            'limit' => $browse_pager->getPageSize() + 1,
          )));
      $browse_paths = $browse_results->getPaths();
      $browse_paths = $browse_pager->sliceResults($browse_paths);

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
      $readme = $this->renderDirectoryReadme($browse_results);
    } else {
      $readme = null;
    }

    $content[] = $this->buildBrowseTable(
      $browse_results,
      $browse_paths,
      $browse_exception,
      $handles,
      $browse_pager);

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

  private function buildHeaderView(PhabricatorRepository $repository) {
    $viewer = $this->getViewer();
    $header = id(new PHUIHeaderView())
      ->setHeader($repository->getName())
      ->setUser($viewer)
      ->setPolicyObject($repository)
      ->setHeaderIcon('fa-code');

    if (!$repository->isTracked()) {
      $header->setStatus('fa-ban', 'dark', pht('Inactive'));
    } else if ($repository->isImporting()) {
      $ratio = $repository->loadImportProgress();
      $percentage = sprintf('%.2f%%', 100 * $ratio);
      $header->setStatus(
        'fa-clock-o',
        'indigo',
        pht('Importing (%s)...', $percentage));
    } else {
      $header->setStatus('fa-check', 'bluegrey', pht('Active'));
    }

    return $header;
  }

  private function buildCurtain(PhabricatorRepository $repository) {
    $viewer = $this->getViewer();

    $edit_uri = $repository->getPathURI('edit/');
    $curtain = $this->newCurtainView($repository);

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $repository,
      PhabricatorPolicyCapability::CAN_EDIT);

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Repository'))
        ->setIcon('fa-pencil')
        ->setHref($edit_uri)
        ->setWorkflow(!$can_edit)
        ->setDisabled(!$can_edit));

    if ($repository->isHosted()) {
      $push_uri = $this->getApplicationURI(
        'pushlog/?repositories='.$repository->getMonogram());

      $curtain->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('View Push Logs'))
          ->setIcon('fa-list-alt')
          ->setHref($push_uri));
    }

    return $curtain;
  }

  private function buildDescriptionView(PhabricatorRepository $repository) {
    $viewer = $this->getViewer();
    $view = id(new PHUIPropertyListView())
      ->setUser($viewer);

    $description = $repository->getDetail('description');
    if (strlen($description)) {
      $description = new PHUIRemarkupView($viewer, $description);
      $view->addTextContent($description);
      return id(new PHUIObjectBoxView())
        ->setHeaderText(pht('Description'))
        ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
        ->appendChild($view);
    }
    return null;
  }

  private function buildPropertiesTable(PhabricatorRepository $repository) {
    $viewer = $this->getViewer();

    $view = id(new PHUIPropertyListView())
      ->setUser($viewer);

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

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Details'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->appendChild($view);

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
    $viewer = $this->getViewer();

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

    $panel = id(new PHUIObjectBoxView())
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY);
    $header = new PHUIHeaderView();
    $header->setHeader(pht('Branches'));

    if ($more_branches) {
      $header->setSubHeader(pht('Showing %d branches.', $limit));
    }

    $button = new PHUIButtonView();
    $button->setText(pht('Show All'));
    $button->setTag('a');
    $button->setIcon('fa-code-fork');
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
    $viewer = $this->getViewer();
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

    $button = new PHUIButtonView();
    $button->setText(pht('Show All Tags'));
    $button->setTag('a');
    $button->setIcon('fa-tag');
    $button->setHref($drequest->generateURI(
      array(
        'action' => 'tags',
      )));

    $header->addActionLink($button);

    $panel->setHeader($header);
    $panel->setTable($view);
    $panel->setBackground(PHUIObjectBoxView::BLUE_PROPERTY);

    return $panel;
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

    $icon = id(new PHUIIconView())
      ->setIcon('fa-list-alt');

    $button = id(new PHUIButtonView())
      ->setText(pht('View History'))
      ->setHref($drequest->generateURI(
        array(
          'action' => 'history',
        )))
      ->setTag('a')
      ->setIcon($icon);

    $panel = id(new PHUIObjectBoxView())
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY);
    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Recent Commits'))
      ->addActionLink($button);
    $panel->setHeader($header);
    $panel->setTable($history_table);

    return $panel;
  }

  private function buildLocateFile() {
    $request = $this->getRequest();
    $viewer = $request->getUser();
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();

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
        ->setHeaderText(pht('Locate File'))
        ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
        ->appendChild($form_box);
    }
    return $locate_panel;
  }

  private function buildBrowseTable(
    $browse_results,
    $browse_paths,
    $browse_exception,
    array $handles,
    PHUIPagerView $pager) {

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

    $browse_panel = id(new PHUIObjectBoxView())
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY);
    $header = id(new PHUIHeaderView())
      ->setHeader($repository->getName());

    $icon = id(new PHUIIconView())
      ->setIcon('fa-folder-open');

    $button = new PHUIButtonView();
    $button->setText(pht('Browse Repository'));
    $button->setTag('a');
    $button->setIcon($icon);
    $button->setHref($browse_uri);

    $header->addActionLink($button);
    $browse_panel->setHeader($header);
    $browse_panel->setTable($browse_table);

    $pager->setURI($browse_uri, 'offset');

    if ($pager->willShowPagingControls()) {
      $pager_box = $this->renderTablePagerBox($pager);
    } else {
      $pager_box = null;
    }

    return array(
      $browse_panel,
      $pager_box,
    );
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
