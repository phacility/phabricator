<?php

final class PhabricatorOwnersDetailController
  extends PhabricatorOwnersController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $package = id(new PhabricatorOwnersPackageQuery())
      ->setViewer($viewer)
      ->withIDs(array($request->getURIData('id')))
      ->needPaths(true)
      ->executeOne();
    if (!$package) {
      return new Aphront404Response();
    }

    $paths = $package->getPaths();

    $repository_phids = array();
    foreach ($paths as $path) {
      $repository_phids[$path->getRepositoryPHID()] = true;
    }

    if ($repository_phids) {
      $repositories = id(new PhabricatorRepositoryQuery())
        ->setViewer($viewer)
        ->withPHIDs(array_keys($repository_phids))
        ->execute();
      $repositories = mpull($repositories, null, 'getPHID');
    } else {
      $repositories = array();
    }

    $field_list = PhabricatorCustomField::getObjectFields(
      $package,
      PhabricatorCustomField::ROLE_VIEW);
    $field_list
      ->setViewer($viewer)
      ->readFieldsFromStorage($package);

    $curtain = $this->buildCurtain($package);
    $details = $this->buildPackageDetailView($package, $field_list);

    if ($package->isArchived()) {
      $header_icon = 'fa-ban';
      $header_name = pht('Archived');
      $header_color = 'dark';
    } else {
      $header_icon = 'fa-check';
      $header_name = pht('Active');
      $header_color = 'bluegrey';
    }

    $header = id(new PHUIHeaderView())
      ->setUser($viewer)
      ->setHeader($package->getName())
      ->setStatus($header_icon, $header_color, $header_name)
      ->setPolicyObject($package)
      ->setHeaderIcon('fa-gift');

    $commit_views = array();

    $params = array(
      'package' => $package->getPHID(),
    );

    $commit_uri = new PhutilURI('/diffusion/commit/', $params);

    $status_concern = DiffusionCommitAuditStatus::CONCERN_RAISED;

    $attention_commits = id(new DiffusionCommitQuery())
      ->setViewer($request->getUser())
      ->withPackagePHIDs(array($package->getPHID()))
      ->withStatuses(
        array(
          $status_concern,
        ))
      ->needCommitData(true)
      ->needAuditRequests(true)
      ->needIdentities(true)
      ->setLimit(10)
      ->execute();
    $view = id(new DiffusionCommitGraphView())
      ->setViewer($viewer)
      ->setCommits($attention_commits)
      ->newObjectItemListView();

    $view->setNoDataString(pht('This package has no open problem commits.'));

    $commit_views[] = array(
      'view'    => $view,
      'header'  => pht('Needs Attention'),
      'icon'    => 'fa-warning',
      'button'  => id(new PHUIButtonView())
        ->setTag('a')
        ->setHref($commit_uri->alter('status', $status_concern))
        ->setIcon('fa-list-ul')
        ->setText(pht('View All')),
    );

    $all_commits = id(new DiffusionCommitQuery())
      ->setViewer($request->getUser())
      ->withPackagePHIDs(array($package->getPHID()))
      ->needCommitData(true)
      ->needAuditRequests(true)
      ->needIdentities(true)
      ->setLimit(25)
      ->execute();

    $view = id(new DiffusionCommitGraphView())
      ->setViewer($viewer)
      ->setCommits($all_commits)
      ->newObjectItemListView();

    $view->setNoDataString(pht('No commits in this package.'));

    $commit_views[] = array(
      'view'    => $view,
      'header'  => pht('Recent Commits'),
      'icon'    => 'fa-code',
      'button'  => id(new PHUIButtonView())
        ->setTag('a')
        ->setHref($commit_uri)
        ->setIcon('fa-list-ul')
        ->setText(pht('View All')),
    );

    $commit_panels = array();
    foreach ($commit_views as $commit_view) {
      $commit_panel = id(new PHUIObjectBoxView())
        ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY);
      $commit_header = id(new PHUIHeaderView())
        ->setHeader($commit_view['header'])
        ->setHeaderIcon($commit_view['icon']);
      if (isset($commit_view['button'])) {
        $commit_header->addActionLink($commit_view['button']);
      }
      $commit_panel->setHeader($commit_header);
      $commit_panel->appendChild($commit_view['view']);

      $commit_panels[] = $commit_panel;
    }

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($package->getMonogram());
    $crumbs->setBorder(true);

    $rules_view = $this->newRulesView($package);

    $timeline = $this->buildTransactionTimeline(
      $package,
      new PhabricatorOwnersPackageTransactionQuery());
    $timeline->setShouldTerminate(true);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setCurtain($curtain)
      ->setMainColumn(array(
        $this->renderPathsTable($paths, $repositories),
        $rules_view,
        $commit_panels,
        $timeline,
      ))
      ->addPropertySection(pht('Details'), $details);

    return $this->newPage()
      ->setTitle($package->getName())
      ->setCrumbs($crumbs)
      ->appendChild($view);
  }

  private function buildPackageDetailView(
    PhabricatorOwnersPackage $package,
    PhabricatorCustomFieldList $field_list) {

    $viewer = $this->getViewer();
    $view = id(new PHUIPropertyListView())
      ->setUser($viewer);

    $owners = $package->getOwners();
    if ($owners) {
      $owner_list = $viewer->renderHandleList(mpull($owners, 'getUserPHID'));
    } else {
      $owner_list = phutil_tag('em', array(), pht('None'));
    }
    $view->addProperty(pht('Owners'), $owner_list);


    $dominion = $package->getDominion();
    $dominion_map = PhabricatorOwnersPackage::getDominionOptionsMap();
    $spec = idx($dominion_map, $dominion, array());
    $name = idx($spec, 'short', $dominion);
    $view->addProperty(pht('Dominion'), $name);

    $authority_mode = $package->getAuthorityMode();
    $authority_map = PhabricatorOwnersPackage::getAuthorityOptionsMap();
    $spec = idx($authority_map, $authority_mode, array());
    $name = idx($spec, 'short', $authority_mode);
    $view->addProperty(pht('Authority'), $name);

    $auto = $package->getAutoReview();
    $autoreview_map = PhabricatorOwnersPackage::getAutoreviewOptionsMap();
    $spec = idx($autoreview_map, $auto, array());
    $name = idx($spec, 'name', $auto);
    $view->addProperty(pht('Auto Review'), $name);

    $rule = $package->newAuditingRule();
    $view->addProperty(pht('Auditing'), $rule->getDisplayName());

    $ignored = $package->getIgnoredPathAttributes();
    $ignored = array_keys($ignored);
    if ($ignored) {
      $ignored = implode(', ', $ignored);
    } else {
      $ignored = phutil_tag('em', array(), pht('None'));
    }

    $view->addProperty(pht('Ignored Attributes'), $ignored);

    $description = $package->getDescription();
    if (strlen($description)) {
      $description = new PHUIRemarkupView($viewer, $description);
      $view->addSectionHeader(pht('Description'));
      $view->addTextContent($description);
    }

    $field_list->appendFieldsToPropertyList(
      $package,
      $viewer,
      $view);

    return $view;
  }

  private function buildCurtain(PhabricatorOwnersPackage $package) {
    $viewer = $this->getViewer();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $package,
      PhabricatorPolicyCapability::CAN_EDIT);

    $id = $package->getID();
    $edit_uri = $this->getApplicationURI("/edit/{$id}/");
    $paths_uri = $this->getApplicationURI("/paths/{$id}/");

    $curtain = $this->newCurtainView($package);

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Package'))
        ->setIcon('fa-pencil')
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit)
        ->setHref($edit_uri));

    if ($package->isArchived()) {
      $curtain->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Activate Package'))
          ->setIcon('fa-check')
          ->setDisabled(!$can_edit)
          ->setWorkflow($can_edit)
          ->setHref($this->getApplicationURI("/archive/{$id}/")));
    } else {
      $curtain->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Archive Package'))
          ->setIcon('fa-ban')
          ->setDisabled(!$can_edit)
          ->setWorkflow($can_edit)
          ->setHref($this->getApplicationURI("/archive/{$id}/")));
    }

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Paths'))
        ->setIcon('fa-folder-open')
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit)
        ->setHref($paths_uri));

    return $curtain;
  }

  private function renderPathsTable(array $paths, array $repositories) {
    $viewer = $this->getViewer();

    $rows = array();
    foreach ($paths as $path) {
      $repo = idx($repositories, $path->getRepositoryPHID());
      if (!$repo) {
        continue;
      }
      $href = $repo->generateURI(
        array(
          'branch'   => $repo->getDefaultBranch(),
          'path'     => $path->getPathDisplay(),
          'action'   => 'browse',
        ));

      $path_link = phutil_tag(
        'a',
        array(
          'href' => (string)$href,
        ),
        $path->getPathDisplay());

      $rows[] = array(
        ($path->getExcluded() ? '-' : '+'),
        $repo->getName(),
        $path_link,
      );
    }

    $info = null;
    if (!$paths) {
      $info = id(new PHUIInfoView())
        ->setSeverity(PHUIInfoView::SEVERITY_WARNING)
        ->setErrors(
          array(
            pht(
              'This package does not contain any paths yet. Use '.
              '"Edit Paths" to add some.'),
          ));
    }

    $table = id(new AphrontTableView($rows))
      ->setHeaders(
        array(
          null,
          pht('Repository'),
          pht('Path'),
        ))
      ->setColumnClasses(
        array(
          null,
          null,
          'wide',
        ));

    if ($info) {
      $table->setNotice($info);
    }

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Paths'))
      ->setHeaderIcon('fa-folder-open');

    $box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setTable($table);

    return $box;
  }

  private function newRulesView(PhabricatorOwnersPackage $package) {
    $viewer = $this->getViewer();

    $limit = 10;
    $rules = id(new HeraldRuleQuery())
      ->setViewer($viewer)
      ->withDisabled(false)
      ->withAffectedObjectPHIDs(array($package->getPHID()))
      ->needValidateAuthors(true)
      ->setLimit($limit + 1)
      ->execute();

    $more_results = (count($rules) > $limit);
    $rules = array_slice($rules, 0, $limit);

    $list = id(new HeraldRuleListView())
      ->setViewer($viewer)
      ->setRules($rules)
      ->newObjectList();

    $list->setNoDataString(
      pht(
        'No active Herald rules add this package as an auditor, reviewer, '.
        'or subscriber.'));

    $more_href = new PhutilURI(
      '/herald/',
      array('affectedPHID' => $package->getPHID()));

    if ($more_results) {
      $list->newTailButton()
        ->setHref($more_href);
    }

    $more_link = id(new PHUIButtonView())
      ->setTag('a')
      ->setIcon('fa-list-ul')
      ->setText(pht('View All Rules'))
      ->setHref($more_href);

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Affected By Herald Rules'))
      ->setHeaderIcon(id(new PhabricatorHeraldApplication())->getIcon())
      ->addActionLink($more_link);

    return id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->appendChild($list);
  }

}
