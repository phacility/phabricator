<?php

final class DiffusionRepositoryController extends DiffusionController {

  private $browseFuture;
  private $branchButton = null;
  private $branchFuture;

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $response = $this->loadDiffusionContext();
    if ($response) {
      return $response;
    }

    require_celerity_resource('diffusion-css');

    $viewer = $this->getViewer();
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();

    $crumbs = $this->buildCrumbs();
    $crumbs->setBorder(true);

    $header = $this->buildHeaderView($repository);
    $actions = $this->buildActionList($repository);
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
        ->needPositions(true)
        ->execute();

      // It's possible that this branch previously existed, but has been
      // deleted. Make sure we have valid cursor positions, not just cursors.
      $any_positions = false;
      foreach ($ref_cursors as $ref_cursor) {
        if ($ref_cursor->getPositions()) {
          $any_positions = true;
          break;
        }
      }

      if ($any_positions) {
        // This is a valid branch, so we necessarily have some content.
        $page_has_content = true;
      } else {
        $default = $repository->getDefaultBranch();
        if ($default != $drequest->getBranch()) {
          $empty_title = pht('No Such Branch');
          $empty_message = pht(
            'There is no branch named "%s" in this repository.',
            $drequest->getBranch());
        } else {
          $empty_title = pht('No Default Branch');
          $empty_message = pht(
            'This repository is configured with default branch "%s"  but '.
            'there is no branch with that name in this repository.',
            $default);
        }
      }
    }

    // If we didn't find any branches, check if there are any commits at all.
    // This can tailor the message for empty repositories.
    $any_commit = null;
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
      // If we have a commit somewhere, find branches.
      // TODO: Evan will replace
      // $this->buildNormalContent($drequest);
      $content = id(new PHUIInfoView())
        ->setTitle($empty_title)
        ->setSeverity(PHUIInfoView::SEVERITY_WARNING)
        ->setErrors(array($empty_message));
    }

    $tabs = $this->buildTabsView('code');

    $clone_uri = $drequest->generateURI(
      array(
        'action' => 'clone',
      ));

    if ($repository->isSVN()) {
      $clone_text = pht('Checkout');
    } else {
      $clone_text = pht('Clone');
    }

    $actions_button = id(new PHUIButtonView())
      ->setTag('a')
      ->setText(pht('Actions'))
      ->setIcon('fa-bars')
      ->addClass('mmr')
      ->setColor(PHUIButtonView::GREY)
      ->setDropdown(true)
      ->setDropdownMenu($actions);

    $clone_button = id(new PHUIButtonView())
      ->setTag('a')
      ->setText($clone_text)
      ->setColor(PHUIButtonView::GREEN)
      ->setIcon('fa-download')
      ->setWorkflow(true)
      ->setHref($clone_uri);

    $bar = id(new PHUILeftRightView())
      ->setLeft($locate_file)
      ->setRight(array($this->branchButton, $actions_button, $clone_button))
      ->addClass('diffusion-action-bar');

    $status_view = null;
    if ($repository->isReadOnly()) {
      $status_view = id(new PHUIInfoView())
        ->setSeverity(PHUIInfoView::SEVERITY_WARNING)
        ->setErrors(
          array(
            phutil_escape_html_newlines(
              $repository->getReadOnlyMessageForDisplay()),
          ));
    }

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter(
        array(
          $status_view,
          $bar,
          $description,
          $content,
        ));

    if ($page_has_content) {
      $view->setTabs($tabs);
    }

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

    $commit = $drequest->getCommit();
    $path = $drequest->getPath();

    $futures = array();

    $browse_pager = id(new PHUIPagerView())
      ->readFromRequest($request);

    $this->browseFuture = $this->callConduitMethod(
      'diffusion.browsequery',
      array(
        'commit' => $commit,
        'path' => $path,
        'limit' => $browse_pager->getPageSize() + 1,
      ));
    $futures[] = $this->browseFuture;

    if ($this->needBranchFuture()) {
      $branch_limit = $this->getBranchLimit();
      $this->branchFuture = $this->callConduitMethod(
        'diffusion.branchquery',
        array(
          'closed' => false,
          'limit' => $branch_limit + 1,
        ));
      $futures[] = $this->branchFuture;
    }

    $futures = array_filter($futures);
    $futures = new FutureIterator($futures);
    foreach ($futures as $future) {
      // Just resolve all the futures before continuing.
    }

    $content = array();

    try {
      $browse_results = $this->browseFuture->resolve();
      $browse_results = DiffusionBrowseResultSet::newFromConduit(
        $browse_results);

      $browse_paths = $browse_results->getPaths();
      $browse_paths = $browse_pager->sliceResults($browse_paths);

      $browse_exception = null;
    } catch (Exception $ex) {
      $browse_results = null;
      $browse_paths = null;
      $browse_exception = $ex;
    }

    if ($browse_results) {
      $readme = $this->renderDirectoryReadme($browse_results);
    } else {
      $readme = null;
    }

    $content[] = $this->buildBrowseTable(
      $browse_results,
      $browse_paths,
      $browse_exception,
      $browse_pager);

    if ($readme) {
      $content[] = $readme;
    }

    try {
      $branch_button = $this->buildBranchList($drequest);
      $this->branchButton = $branch_button;
    } catch (Exception $ex) {
      if (!$repository->isImporting()) {
        $content[] = $this->renderStatusMessage(
          pht('Unable to Load Branches'),
          $ex->getMessage());
      }
    }

    return $content;
  }

  private function buildHeaderView(PhabricatorRepository $repository) {
    $viewer = $this->getViewer();
    $drequest = $this->getDiffusionRequest();
    $search = $this->renderSearchForm();

    $header = id(new PHUIHeaderView())
      ->setHeader($repository->getName())
      ->setUser($viewer)
      ->setPolicyObject($repository)
      ->setProfileHeader(true)
      ->setImage($repository->getProfileImageURI())
      ->setImageEditURL('/diffusion/picture/'.$repository->getID().'/')
      ->addActionItem($search)
      ->addClass('diffusion-profile-header');

    if (!$repository->isTracked()) {
      $header->setStatus('fa-ban', 'dark', pht('Inactive'));
    } else if ($repository->isReadOnly()) {
      $header->setStatus('fa-wrench', 'indigo', pht('Under Maintenance'));
    } else if ($repository->isImporting()) {
      $ratio = $repository->loadImportProgress();
      $percentage = sprintf('%.2f%%', 100 * $ratio);
      $header->setStatus(
        'fa-clock-o',
        'indigo',
        pht('Importing (%s)...', $percentage));
    } else if ($repository->isPublishingDisabled()) {
      $header->setStatus('fa-minus', 'bluegrey', pht('Publishing Disabled'));
    } else {
      $header->setStatus('fa-check', 'bluegrey', pht('Active'));
    }

    if (!$repository->isSVN()) {
      $default = $repository->getDefaultBranch();
      if ($default != $drequest->getBranch()) {
        $branch_tag = $this->renderBranchTag($drequest);
        $header->addTag($branch_tag);
      }
    }

    return $header;
  }

  private function buildActionList(PhabricatorRepository $repository) {
    $viewer = $this->getViewer();

    $edit_uri = $repository->getPathURI('manage/');
    $action_view = id(new PhabricatorActionListView())
      ->setUser($viewer)
      ->setObject($repository);

    $action_view->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Manage Repository'))
        ->setIcon('fa-cogs')
        ->setHref($edit_uri));

    if ($repository->isHosted()) {
      $push_uri = $this->getApplicationURI(
        'pushlog/?repositories='.$repository->getPHID());

      $action_view->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('View Push Logs'))
          ->setIcon('fa-upload')
          ->setHref($push_uri));

      $pull_uri = $this->getApplicationURI(
        'synclog/?repositories='.$repository->getPHID());

      $action_view->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('View Sync Logs'))
          ->setIcon('fa-exchange')
          ->setHref($pull_uri));
    }

    $pull_uri = $this->getApplicationURI(
      'pulllog/?repositories='.$repository->getPHID());

    $action_view->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('View Pull Logs'))
        ->setIcon('fa-download')
        ->setHref($pull_uri));

    return $action_view;
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
        ->appendChild($view)
        ->addClass('diffusion-profile-description');
    }
    return null;
  }

  private function buildBranchList(DiffusionRequest $drequest) {
    $viewer = $this->getViewer();

    if (!$this->needBranchFuture()) {
      return null;
    }

    $branches = $this->branchFuture->resolve();
    if (!$branches) {
      return null;
    }

    $limit = $this->getBranchLimit();
    $more_branches = (count($branches) > $limit);
    $branches = array_slice($branches, 0, $limit);

    $branches = DiffusionRepositoryRef::loadAllFromDictionaries($branches);

    $actions = id(new PhabricatorActionListView())
      ->setViewer($viewer);

    foreach ($branches as $branch) {
      $branch_uri = $drequest->generateURI(
        array(
          'action' => 'browse',
          'branch' => $branch->getShortname(),
        ));
      $actions->addAction(
        id(new PhabricatorActionView())
          ->setName($branch->getShortname())
          ->setIcon('fa-code-fork')
          ->setHref($branch_uri));
    }

    if ($more_branches) {
      $more_uri = $drequest->generateURI(
        array(
          'action' => 'branches',
        ));
      $actions->addAction(
        id(new PhabricatorActionView())
          ->setType(PhabricatorActionView::TYPE_DIVIDER));
      $actions->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('See More Branches'))
          ->setIcon('fa-external-link')
          ->setHref($more_uri));
    }

    $button = id(new PHUIButtonView())
      ->setText(pht('Branch: %s', $drequest->getBranch()))
      ->setTag('a')
      ->addClass('mmr')
      ->setIcon('fa-code-fork')
      ->setColor(PHUIButtonView::GREY)
      ->setDropdown(true)
      ->setDropdownMenu($actions);

    return $button;
  }

  private function buildLocateFile() {
    $request = $this->getRequest();
    $viewer = $request->getUser();
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();

    $form_box = null;
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
            ->setPlaceholder(pht('Locate File')));
      $form_box = id(new PHUIBoxView())
        ->appendChild($form->buildLayoutView())
        ->addClass('diffusion-profile-locate');
    }
    return $form_box;
  }

  private function buildBrowseTable(
    $browse_results,
    $browse_paths,
    $browse_exception,
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
      ->setDiffusionRequest($drequest);
    if ($browse_paths) {
      $browse_table->setPaths($browse_paths);
    } else {
      $browse_table->setPaths(array());
    }

    $browse_uri = $drequest->generateURI(array('action' => 'browse'));
    $pager->setURI($browse_uri, 'offset');

    $repository_name = $repository->getName();
    $branch_name = $drequest->getBranch();
    if (strlen($branch_name)) {
      $repository_name .= ' ('.$branch_name.')';
    }

    $header = phutil_tag(
      'a',
      array(
        'href' => $browse_uri,
        'class' => 'diffusion-view-browse-header',
      ),
      $repository_name);

    return id(new PHUIObjectBoxView())
      ->setHeaderText($header)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setTable($browse_table)
      ->addClass('diffusion-mobile-view')
      ->setPager($pager);
  }

  private function needBranchFuture() {
    $drequest = $this->getDiffusionRequest();

    if ($drequest->getBranch() === null) {
      return false;
    }

    return true;
  }

  private function getBranchLimit() {
    return 15;
  }

}
