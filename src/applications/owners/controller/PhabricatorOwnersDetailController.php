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
      ->needOwners(true)
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

    $actions = $this->buildPackageActionView($package);
    $properties = $this->buildPackagePropertyView($package, $field_list);
    $properties->setActionList($actions);

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
      ->setPolicyObject($package);

    $panel = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($properties);

    $commit_views = array();

    $commit_uri = id(new PhutilURI('/audit/'))
      ->setQueryParams(
        array(
          'auditorPHIDs' => $package->getPHID(),
        ));

    $status_concern = DiffusionCommitQuery::AUDIT_STATUS_CONCERN;

    $attention_commits = id(new DiffusionCommitQuery())
      ->setViewer($request->getUser())
      ->withAuditorPHIDs(array($package->getPHID()))
      ->withAuditStatus($status_concern)
      ->needCommitData(true)
      ->setLimit(10)
      ->execute();
    $view = id(new PhabricatorAuditListView())
      ->setUser($viewer)
      ->setNoDataString(pht('This package has no open problem commits.'))
      ->setCommits($attention_commits);

    $commit_views[] = array(
      'view'    => $view,
      'header'  => pht('Commits in this Package that Need Attention'),
      'button'  => id(new PHUIButtonView())
        ->setTag('a')
        ->setHref($commit_uri->alter('status', $status_concern))
        ->setText(pht('View All Problem Commits')),
    );

    $all_commits = id(new DiffusionCommitQuery())
      ->setViewer($request->getUser())
      ->withAuditorPHIDs(array($package->getPHID()))
      ->needCommitData(true)
      ->setLimit(100)
      ->execute();

    $view = id(new PhabricatorAuditListView())
      ->setUser($viewer)
      ->setCommits($all_commits)
      ->setNoDataString(pht('No commits in this package.'));

    $commit_views[] = array(
      'view'    => $view,
      'header'  => pht('Recent Commits in Package'),
      'button'  => id(new PHUIButtonView())
        ->setTag('a')
        ->setHref($commit_uri)
        ->setText(pht('View All Package Commits')),
    );

    $phids = array();
    foreach ($commit_views as $commit_view) {
      $phids[] = $commit_view['view']->getRequiredHandlePHIDs();
    }
    $phids = array_mergev($phids);
    $handles = $this->loadViewerHandles($phids);

    $commit_panels = array();
    foreach ($commit_views as $commit_view) {
      $commit_panel = new PHUIObjectBoxView();
      $header = new PHUIHeaderView();
      $header->setHeader($commit_view['header']);
      if (isset($commit_view['button'])) {
        $header->addActionLink($commit_view['button']);
      }
      $commit_view['view']->setHandles($handles);
      $commit_panel->setHeader($header);
      $commit_panel->appendChild($commit_view['view']);

      $commit_panels[] = $commit_panel;
    }

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($package->getName());

    $timeline = $this->buildTransactionTimeline(
      $package,
      new PhabricatorOwnersPackageTransactionQuery());
    $timeline->setShouldTerminate(true);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $panel,
        $this->renderPathsTable($paths, $repositories),
        $commit_panels,
        $timeline,
      ),
      array(
        'title' => $package->getName(),
      ));
  }


  private function buildPackagePropertyView(
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

    if ($package->getAuditingEnabled()) {
      $auditing = pht('Enabled');
    } else {
      $auditing = pht('Disabled');
    }
    $view->addProperty(pht('Auditing'), $auditing);

    $description = $package->getDescription();
    if (strlen($description)) {
      $view->addSectionHeader(
        pht('Description'), PHUIPropertyListView::ICON_SUMMARY);
      $view->addTextContent(
        $output = PhabricatorMarkupEngine::renderOneObject(
          id(new PhabricatorMarkupOneOff())->setContent($description),
          'default',
          $viewer));
    }

    $view->invokeWillRenderEvent();

    $field_list->appendFieldsToPropertyList(
      $package,
      $viewer,
      $view);

    return $view;
  }

  private function buildPackageActionView(PhabricatorOwnersPackage $package) {
    $viewer = $this->getViewer();

    // TODO: Implement this capability.
    $can_edit = true;

    $id = $package->getID();
    $edit_uri = $this->getApplicationURI("/edit/{$id}/");
    $paths_uri = $this->getApplicationURI("/paths/{$id}/");

    $view = id(new PhabricatorActionListView())
      ->setUser($viewer)
      ->setObject($package)
      ->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Edit Package'))
          ->setIcon('fa-pencil')
          ->setDisabled(!$can_edit)
          ->setWorkflow(!$can_edit)
          ->setHref($edit_uri))
      ->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Edit Paths'))
          ->setIcon('fa-folder-open')
          ->setDisabled(!$can_edit)
          ->setWorkflow(!$can_edit)
          ->setHref($paths_uri));

    return $view;
  }

  private function renderPathsTable(array $paths, array $repositories) {
    $viewer = $this->getViewer();

    $rows = array();
    foreach ($paths as $path) {
      $repo = idx($repositories, $path->getRepositoryPHID());
      if (!$repo) {
        continue;
      }
      $href = DiffusionRequest::generateDiffusionURI(
        array(
          'callsign' => $repo->getCallsign(),
          'branch'   => $repo->getDefaultBranch(),
          'path'     => $path->getPath(),
          'action'   => 'browse',
        ));

      $path_link = phutil_tag(
        'a',
        array(
          'href' => (string)$href,
        ),
        $path->getPath());

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

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Paths'))
      ->setTable($table);

    if ($info) {
      $box->setInfoView($info);
    }

    return $box;
  }

}
