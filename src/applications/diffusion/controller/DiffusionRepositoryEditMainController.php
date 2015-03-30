<?php

final class DiffusionRepositoryEditMainController
  extends DiffusionRepositoryEditController {

  protected function processDiffusionRequest(AphrontRequest $request) {
    $viewer = $request->getUser();
    $drequest = $this->diffusionRequest;
    $repository = $drequest->getRepository();

    PhabricatorPolicyFilter::requireCapability(
      $viewer,
      $repository,
      PhabricatorPolicyCapability::CAN_EDIT);

    $is_svn = false;
    $is_git = false;
    $is_hg = false;
    switch ($repository->getVersionControlSystem()) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
        $is_git = true;
        break;
      case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
        $is_svn = true;
        break;
      case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
        $is_hg = true;
        break;
    }

    $has_branches = ($is_git || $is_hg);
    $has_local = $repository->usesLocalWorkingCopy();

    $crumbs = $this->buildApplicationCrumbs($is_main = true);

    $title = pht('Edit %s', $repository->getName());

    $header = id(new PHUIHeaderView())
      ->setHeader($title);
    if ($repository->isTracked()) {
      $header->setStatus('fa-check', 'bluegrey', pht('Active'));
    } else {
      $header->setStatus('fa-ban', 'dark', pht('Inactive'));
    }

    $basic_actions = $this->buildBasicActions($repository);
    $basic_properties =
      $this->buildBasicProperties($repository, $basic_actions);

    $policy_actions = $this->buildPolicyActions($repository);
    $policy_properties =
      $this->buildPolicyProperties($repository, $policy_actions);

    $remote_properties = null;
    if (!$repository->isHosted()) {
      $remote_properties = $this->buildRemoteProperties(
        $repository,
        $this->buildRemoteActions($repository));
    }

    $encoding_actions = $this->buildEncodingActions($repository);
    $encoding_properties =
      $this->buildEncodingProperties($repository, $encoding_actions);

    $hosting_properties = $this->buildHostingProperties(
      $repository,
      $this->buildHostingActions($repository));


    $branches_properties = null;
    if ($has_branches) {
      $branches_properties = $this->buildBranchesProperties(
        $repository,
        $this->buildBranchesActions($repository));
    }

    $subversion_properties = null;
    if ($is_svn) {
      $subversion_properties = $this->buildSubversionProperties(
        $repository,
        $this->buildSubversionActions($repository));
    }

    $storage_properties = null;
    if ($has_local) {
      $storage_properties = $this->buildStorageProperties(
        $repository,
        $this->buildStorageActions($repository));
    }

    $actions_properties = $this->buildActionsProperties(
      $repository,
      $this->buildActionsActions($repository));

    $timeline = $this->buildTransactionTimeline(
      $repository,
      new PhabricatorRepositoryTransactionQuery());
    $timeline->setShouldTerminate(true);

    $boxes = array();

    $boxes[] = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($basic_properties);

    $boxes[] = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Policies'))
      ->addPropertyList($policy_properties);

    $boxes[] = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Hosting'))
      ->addPropertyList($hosting_properties);

    if ($repository->canMirror()) {
      $mirror_actions = $this->buildMirrorActions($repository);
      $mirror_properties = $this->buildMirrorProperties(
        $repository,
        $mirror_actions);

      $mirrors = id(new PhabricatorRepositoryMirrorQuery())
        ->setViewer($viewer)
        ->withRepositoryPHIDs(array($repository->getPHID()))
        ->execute();

      $mirror_list = $this->buildMirrorList($repository, $mirrors);

      $boxes[] = id(new PhabricatorAnchorView())->setAnchorName('mirrors');

      $mirror_info = array();
      if (PhabricatorEnv::getEnvConfig('phabricator.silent')) {
        $mirror_info[] = pht(
          'Phabricator is running in silent mode, so changes will not '.
          'be pushed to mirrors.');
      }

      $boxes[] = id(new PHUIObjectBoxView())
        ->setFormErrors($mirror_info)
        ->setHeaderText(pht('Mirrors'))
        ->addPropertyList($mirror_properties);

      $boxes[] = $mirror_list;
    }

    if ($remote_properties) {
      $boxes[] = id(new PHUIObjectBoxView())
        ->setHeaderText(pht('Remote'))
        ->addPropertyList($remote_properties);
    }

    if ($storage_properties) {
      $boxes[] = id(new PHUIObjectBoxView())
        ->setHeaderText(pht('Storage'))
        ->addPropertyList($storage_properties);
    }

    $boxes[] = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Text Encoding'))
      ->addPropertyList($encoding_properties);

    if ($branches_properties) {
      $boxes[] = id(new PHUIObjectBoxView())
        ->setHeaderText(pht('Branches'))
        ->addPropertyList($branches_properties);
    }

    if ($subversion_properties) {
      $boxes[] = id(new PHUIObjectBoxView())
        ->setHeaderText(pht('Subversion'))
        ->addPropertyList($subversion_properties);
    }

    $boxes[] = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Actions'))
      ->addPropertyList($actions_properties);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $boxes,
        $timeline,
      ),
      array(
        'title' => $title,
      ));
  }

  private function buildBasicActions(PhabricatorRepository $repository) {
    $viewer = $this->getRequest()->getUser();

    $view = id(new PhabricatorActionListView())
      ->setObjectURI($this->getRequest()->getRequestURI())
      ->setUser($viewer);

    $edit = id(new PhabricatorActionView())
      ->setIcon('fa-pencil')
      ->setName(pht('Edit Basic Information'))
      ->setHref($this->getRepositoryControllerURI($repository, 'edit/basic/'));
    $view->addAction($edit);

    $edit = id(new PhabricatorActionView())
      ->setIcon('fa-refresh')
      ->setName(pht('Update Now'))
      ->setWorkflow(true)
      ->setHref(
        $this->getRepositoryControllerURI($repository, 'edit/update/'));
    $view->addAction($edit);

    $activate = id(new PhabricatorActionView())
      ->setHref(
        $this->getRepositoryControllerURI($repository, 'edit/activate/'))
      ->setWorkflow(true);

    if ($repository->isTracked()) {
      $activate
        ->setIcon('fa-pause')
        ->setName(pht('Deactivate Repository'));
    } else {
      $activate
        ->setIcon('fa-play')
        ->setName(pht('Activate Repository'));
    }

    $view->addAction($activate);

    $view->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Delete Repository'))
        ->setIcon('fa-times')
        ->setHref(
          $this->getRepositoryControllerURI($repository, 'edit/delete/'))
        ->setDisabled(true)
        ->setWorkflow(true));

    return $view;
  }

  private function buildBasicProperties(
    PhabricatorRepository $repository,
    PhabricatorActionListView $actions) {

    $viewer = $this->getRequest()->getUser();

    $view = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setActionList($actions);

    $type = PhabricatorRepositoryType::getNameForRepositoryType(
      $repository->getVersionControlSystem());

    $view->addProperty(pht('Type'), $type);
    $view->addProperty(pht('Callsign'), $repository->getCallsign());


    $clone_name = $repository->getDetail('clone-name');

    if ($repository->isHosted()) {
      $view->addProperty(
        pht('Clone/Checkout As'),
        $clone_name
          ? $clone_name.'/'
          : phutil_tag('em', array(), $repository->getCloneName().'/'));
    }

    $project_phids = PhabricatorEdgeQuery::loadDestinationPHIDs(
      $repository->getPHID(),
      PhabricatorProjectObjectHasProjectEdgeType::EDGECONST);
    if ($project_phids) {
      $project_text = $viewer->renderHandleList($project_phids);
    } else {
      $project_text = phutil_tag('em', array(), pht('None'));
    }
    $view->addProperty(
      pht('Projects'),
      $project_text);

    $view->addProperty(
      pht('Status'),
      $this->buildRepositoryStatus($repository));

    $view->addProperty(
      pht('Update Frequency'),
      $this->buildRepositoryUpdateInterval($repository));

    $description = $repository->getDetail('description');
    $view->addSectionHeader(pht('Description'));
    if (!strlen($description)) {
      $description = phutil_tag('em', array(), pht('No description provided.'));
    } else {
      $description = PhabricatorMarkupEngine::renderOneObject(
        $repository,
        'description',
        $viewer);
    }
    $view->addTextContent($description);

    return $view;
  }

  private function buildEncodingActions(PhabricatorRepository $repository) {
    $viewer = $this->getRequest()->getUser();

    $view = id(new PhabricatorActionListView())
      ->setObjectURI($this->getRequest()->getRequestURI())
      ->setUser($viewer);

    $edit = id(new PhabricatorActionView())
      ->setIcon('fa-pencil')
      ->setName(pht('Edit Text Encoding'))
      ->setHref(
        $this->getRepositoryControllerURI($repository, 'edit/encoding/'));
    $view->addAction($edit);

    return $view;
  }

  private function buildEncodingProperties(
    PhabricatorRepository $repository,
    PhabricatorActionListView $actions) {

    $viewer = $this->getRequest()->getUser();

    $view = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setActionList($actions);

    $encoding = $repository->getDetail('encoding');
    if (!$encoding) {
      $encoding = phutil_tag('em', array(), pht('Use Default (UTF-8)'));
    }

    $view->addProperty(pht('Encoding'), $encoding);

    return $view;
  }

  private function buildPolicyActions(PhabricatorRepository $repository) {
    $viewer = $this->getRequest()->getUser();

    $view = id(new PhabricatorActionListView())
      ->setObjectURI($this->getRequest()->getRequestURI())
      ->setUser($viewer);

    $edit = id(new PhabricatorActionView())
      ->setIcon('fa-pencil')
      ->setName(pht('Edit Policies'))
      ->setHref(
        $this->getRepositoryControllerURI($repository, 'edit/policy/'));
    $view->addAction($edit);

    return $view;
  }

  private function buildPolicyProperties(
    PhabricatorRepository $repository,
    PhabricatorActionListView $actions) {

    $viewer = $this->getRequest()->getUser();

    $view = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setActionList($actions);

    $descriptions = PhabricatorPolicyQuery::renderPolicyDescriptions(
      $viewer,
      $repository);

    $view->addProperty(
      pht('Visible To'),
      $descriptions[PhabricatorPolicyCapability::CAN_VIEW]);

    $view->addProperty(
      pht('Editable By'),
      $descriptions[PhabricatorPolicyCapability::CAN_EDIT]);

    $pushable = $repository->isHosted()
      ? $descriptions[DiffusionPushCapability::CAPABILITY]
      : phutil_tag('em', array(), pht('Not a Hosted Repository'));
    $view->addProperty(pht('Pushable By'), $pushable);

    return $view;
  }

  private function buildBranchesActions(PhabricatorRepository $repository) {
    $viewer = $this->getRequest()->getUser();

    $view = id(new PhabricatorActionListView())
      ->setObjectURI($this->getRequest()->getRequestURI())
      ->setUser($viewer);

    $edit = id(new PhabricatorActionView())
      ->setIcon('fa-pencil')
      ->setName(pht('Edit Branches'))
      ->setHref(
        $this->getRepositoryControllerURI($repository, 'edit/branches/'));
    $view->addAction($edit);

    return $view;
  }

  private function buildBranchesProperties(
    PhabricatorRepository $repository,
    PhabricatorActionListView $actions) {

    $viewer = $this->getRequest()->getUser();

    $view = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setActionList($actions);

    $default_branch = nonempty(
      $repository->getHumanReadableDetail('default-branch'),
      phutil_tag('em', array(), $repository->getDefaultBranch()));
    $view->addProperty(pht('Default Branch'), $default_branch);

    $track_only = nonempty(
      $repository->getHumanReadableDetail('branch-filter', array()),
      phutil_tag('em', array(), pht('Track All Branches')));
    $view->addProperty(pht('Track Only'), $track_only);

    $autoclose_only = nonempty(
      $repository->getHumanReadableDetail('close-commits-filter', array()),
      phutil_tag('em', array(), pht('Autoclose On All Branches')));

    if ($repository->getDetail('disable-autoclose')) {
      $autoclose_only = phutil_tag('em', array(), pht('Disabled'));
    }

    $view->addProperty(pht('Autoclose Only'), $autoclose_only);

    return $view;
  }

  private function buildSubversionActions(PhabricatorRepository $repository) {
    $viewer = $this->getRequest()->getUser();

    $view = id(new PhabricatorActionListView())
      ->setObjectURI($this->getRequest()->getRequestURI())
      ->setUser($viewer);

    $edit = id(new PhabricatorActionView())
      ->setIcon('fa-pencil')
      ->setName(pht('Edit Subversion Info'))
      ->setHref(
        $this->getRepositoryControllerURI($repository, 'edit/subversion/'));
    $view->addAction($edit);

    return $view;
  }

  private function buildSubversionProperties(
    PhabricatorRepository $repository,
    PhabricatorActionListView $actions) {

    $viewer = $this->getRequest()->getUser();

    $view = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setActionList($actions);

    $svn_uuid = nonempty(
      $repository->getUUID(),
      phutil_tag('em', array(), pht('Not Configured')));
    $view->addProperty(pht('Subversion UUID'), $svn_uuid);

    $svn_subpath = nonempty(
      $repository->getHumanReadableDetail('svn-subpath'),
      phutil_tag('em', array(), pht('Import Entire Repository')));
    $view->addProperty(pht('Import Only'), $svn_subpath);

    return $view;
  }

  private function buildActionsActions(PhabricatorRepository $repository) {
    $viewer = $this->getRequest()->getUser();

    $view = id(new PhabricatorActionListView())
      ->setObjectURI($this->getRequest()->getRequestURI())
      ->setUser($viewer);

    $edit = id(new PhabricatorActionView())
      ->setIcon('fa-pencil')
      ->setName(pht('Edit Actions'))
      ->setHref(
        $this->getRepositoryControllerURI($repository, 'edit/actions/'));
    $view->addAction($edit);

    return $view;
  }

  private function buildActionsProperties(
    PhabricatorRepository $repository,
    PhabricatorActionListView $actions) {

    $viewer = $this->getRequest()->getUser();

    $view = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setActionList($actions);

    $notify = $repository->getDetail('herald-disabled')
      ? pht('Off')
      : pht('On');
    $notify = phutil_tag('em', array(), $notify);
    $view->addProperty(pht('Publish/Notify'), $notify);

    $autoclose = $repository->getDetail('disable-autoclose')
      ? pht('Off')
      : pht('On');
    $autoclose = phutil_tag('em', array(), $autoclose);
    $view->addProperty(pht('Autoclose'), $autoclose);

    return $view;
  }

  private function buildRemoteActions(PhabricatorRepository $repository) {
    $viewer = $this->getRequest()->getUser();

    $view = id(new PhabricatorActionListView())
      ->setObjectURI($this->getRequest()->getRequestURI())
      ->setUser($viewer);

    $edit = id(new PhabricatorActionView())
      ->setIcon('fa-pencil')
      ->setName(pht('Edit Remote'))
      ->setHref(
        $this->getRepositoryControllerURI($repository, 'edit/remote/'));
    $view->addAction($edit);

    return $view;
  }

  private function buildRemoteProperties(
    PhabricatorRepository $repository,
    PhabricatorActionListView $actions) {

    $viewer = $this->getRequest()->getUser();

    $view = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setActionList($actions);

    $view->addProperty(
      pht('Remote URI'),
      $repository->getHumanReadableDetail('remote-uri'));

    $credential_phid = $repository->getCredentialPHID();
    if ($credential_phid) {
      $view->addProperty(
        pht('Credential'),
        $viewer->renderHandle($credential_phid));
    }

    return $view;
  }

  private function buildStorageActions(PhabricatorRepository $repository) {
    $viewer = $this->getRequest()->getUser();

    $view = id(new PhabricatorActionListView())
      ->setObjectURI($this->getRequest()->getRequestURI())
      ->setUser($viewer);

    $edit = id(new PhabricatorActionView())
      ->setIcon('fa-pencil')
      ->setName(pht('Edit Storage'))
      ->setHref(
        $this->getRepositoryControllerURI($repository, 'edit/storage/'));
    $view->addAction($edit);

    return $view;
  }

  private function buildStorageProperties(
    PhabricatorRepository $repository,
    PhabricatorActionListView $actions) {

    $viewer = $this->getRequest()->getUser();

    $view = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setActionList($actions);

    $service_phid = $repository->getAlmanacServicePHID();
    if ($service_phid) {
      $v_service = $viewer->renderHandle($service_phid);
    } else {
      $v_service = phutil_tag(
        'em',
        array(),
        pht('Local'));
    }

    $view->addProperty(
      pht('Storage Service'),
      $v_service);

    $view->addProperty(
      pht('Storage Path'),
      $repository->getHumanReadableDetail('local-path'));

    return $view;
  }

  private function buildHostingActions(PhabricatorRepository $repository) {
    $user = $this->getRequest()->getUser();

    $view = id(new PhabricatorActionListView())
      ->setObjectURI($this->getRequest()->getRequestURI())
      ->setUser($user);

    $edit = id(new PhabricatorActionView())
      ->setIcon('fa-pencil')
      ->setName(pht('Edit Hosting'))
      ->setHref(
        $this->getRepositoryControllerURI($repository, 'edit/hosting/'));
    $view->addAction($edit);

    if ($repository->canAllowDangerousChanges()) {
      if ($repository->shouldAllowDangerousChanges()) {
        $changes = id(new PhabricatorActionView())
          ->setIcon('fa-shield')
          ->setName(pht('Prevent Dangerous Changes'))
          ->setHref(
            $this->getRepositoryControllerURI($repository, 'edit/dangerous/'))
          ->setWorkflow(true);
      } else {
        $changes = id(new PhabricatorActionView())
          ->setIcon('fa-bullseye')
          ->setName(pht('Allow Dangerous Changes'))
          ->setHref(
            $this->getRepositoryControllerURI($repository, 'edit/dangerous/'))
          ->setWorkflow(true);
      }
      $view->addAction($changes);
    }

    return $view;
  }

  private function buildHostingProperties(
    PhabricatorRepository $repository,
    PhabricatorActionListView $actions) {

    $user = $this->getRequest()->getUser();

    $view = id(new PHUIPropertyListView())
      ->setUser($user)
      ->setActionList($actions);

    $hosting = $repository->isHosted()
      ? pht('Hosted on Phabricator')
      : pht('Hosted Elsewhere');
    $view->addProperty(pht('Hosting'), phutil_tag('em', array(), $hosting));

    $view->addProperty(
      pht('Serve over HTTP'),
      phutil_tag(
        'em',
        array(),
        PhabricatorRepository::getProtocolAvailabilityName(
          $repository->getServeOverHTTP())));

    $view->addProperty(
      pht('Serve over SSH'),
      phutil_tag(
        'em',
        array(),
        PhabricatorRepository::getProtocolAvailabilityName(
          $repository->getServeOverSSH())));

    if ($repository->canAllowDangerousChanges()) {
      if ($repository->shouldAllowDangerousChanges()) {
        $description = pht('Allowed');
      } else {
        $description = pht('Not Allowed');
      }

      $view->addProperty(
        pht('Dangerous Changes'),
        $description);
    }

    return $view;
  }

  private function buildRepositoryStatus(
    PhabricatorRepository $repository) {

    $viewer = $this->getRequest()->getUser();
    $is_cluster = $repository->getAlmanacServicePHID();

    $view = new PHUIStatusListView();

    $messages = id(new PhabricatorRepositoryStatusMessage())
      ->loadAllWhere('repositoryID = %d', $repository->getID());
    $messages = mpull($messages, null, 'getStatusType');

    if ($repository->isTracked()) {
      $view->addItem(
        id(new PHUIStatusItemView())
          ->setIcon(PHUIStatusItemView::ICON_ACCEPT, 'green')
          ->setTarget(pht('Repository Active')));
    } else {
      $view->addItem(
        id(new PHUIStatusItemView())
          ->setIcon(PHUIStatusItemView::ICON_WARNING, 'bluegrey')
          ->setTarget(pht('Repository Inactive'))
          ->setNote(
            pht('Activate this repository to begin or resume import.')));
      return $view;
    }

    $binaries = array();
    $svnlook_check = false;
    switch ($repository->getVersionControlSystem()) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
        $binaries[] = 'git';
        break;
      case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
        $binaries[] = 'svn';
        break;
      case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
        $binaries[] = 'hg';
        break;
    }

    if ($repository->isHosted()) {
      if ($repository->getServeOverHTTP() != PhabricatorRepository::SERVE_OFF) {
        switch ($repository->getVersionControlSystem()) {
          case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
            $binaries[] = 'git-http-backend';
            break;
          case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
            $binaries[] = 'svnserve';
            $binaries[] = 'svnadmin';
            $binaries[] = 'svnlook';
            $svnlook_check = true;
            break;
          case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
            $binaries[] = 'hg';
            break;
        }
      }
      if ($repository->getServeOverSSH() != PhabricatorRepository::SERVE_OFF) {
        switch ($repository->getVersionControlSystem()) {
          case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
            $binaries[] = 'git-receive-pack';
            $binaries[] = 'git-upload-pack';
            break;
          case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
            $binaries[] = 'svnserve';
            $binaries[] = 'svnadmin';
            $binaries[] = 'svnlook';
            $svnlook_check = true;
            break;
          case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
            $binaries[] = 'hg';
            break;
        }
      }
    }

    $binaries = array_unique($binaries);
    if (!$is_cluster) {
      // We're only checking for binaries if we aren't running with a cluster
      // configuration. In theory, we could check for binaries on the
      // repository host machine, but we'd need to make this more complicated
      // to do that.

      foreach ($binaries as $binary) {
        $where = Filesystem::resolveBinary($binary);
        if (!$where) {
          $view->addItem(
            id(new PHUIStatusItemView())
              ->setIcon(PHUIStatusItemView::ICON_WARNING, 'red')
              ->setTarget(
                pht('Missing Binary %s', phutil_tag('tt', array(), $binary)))
              ->setNote(pht(
                "Unable to find this binary in the webserver's PATH. You may ".
                "need to configure %s.",
                $this->getEnvConfigLink())));
        } else {
          $view->addItem(
            id(new PHUIStatusItemView())
              ->setIcon(PHUIStatusItemView::ICON_ACCEPT, 'green')
              ->setTarget(
                pht('Found Binary %s', phutil_tag('tt', array(), $binary)))
              ->setNote(phutil_tag('tt', array(), $where)));
        }
      }

      // This gets checked generically above. However, for svn commit hooks, we
      // need this to be in environment.append-paths because subversion strips
      // PATH.
      if ($svnlook_check) {
        $where = Filesystem::resolveBinary('svnlook');
        if ($where) {
          $path = substr($where, 0, strlen($where) - strlen('svnlook'));
          $dirs = PhabricatorEnv::getEnvConfig('environment.append-paths');
          $in_path = false;
          foreach ($dirs as $dir) {
            if (Filesystem::isDescendant($path, $dir)) {
              $in_path = true;
              break;
            }
          }
          if (!$in_path) {
            $view->addItem(
              id(new PHUIStatusItemView())
              ->setIcon(PHUIStatusItemView::ICON_WARNING, 'red')
              ->setTarget(
                pht('Missing Binary %s', phutil_tag('tt', array(), $binary)))
              ->setNote(pht(
                  'Unable to find this binary in `environment.append-paths`. '.
                  'You need to configure %s and include %s.',
                  $this->getEnvConfigLink(),
                  $path)));
          }
        }
      }
    }

    $doc_href = PhabricatorEnv::getDocLink('Managing Daemons with phd');

    $daemon_instructions = pht(
      'Use %s to start daemons. See %s.',
      phutil_tag('tt', array(), 'bin/phd start'),
      phutil_tag(
        'a',
        array(
          'href' => $doc_href,
        ),
        pht('Managing Daemons with phd')));


    $pull_daemon = id(new PhabricatorDaemonLogQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withStatus(PhabricatorDaemonLogQuery::STATUS_ALIVE)
      ->withDaemonClasses(array('PhabricatorRepositoryPullLocalDaemon'))
      ->setLimit(1)
      ->execute();

    if ($pull_daemon) {

      // TODO: In a cluster environment, we need a daemon on this repository's
      // host, specifically, and we aren't checking for that right now. This
      // is a reasonable proxy for things being more-or-less correctly set up,
      // though.

      $view->addItem(
        id(new PHUIStatusItemView())
          ->setIcon(PHUIStatusItemView::ICON_ACCEPT, 'green')
          ->setTarget(pht('Pull Daemon Running')));
    } else {
      $view->addItem(
        id(new PHUIStatusItemView())
          ->setIcon(PHUIStatusItemView::ICON_WARNING, 'red')
          ->setTarget(pht('Pull Daemon Not Running'))
          ->setNote($daemon_instructions));
    }


    $task_daemon = id(new PhabricatorDaemonLogQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withStatus(PhabricatorDaemonLogQuery::STATUS_ALIVE)
      ->withDaemonClasses(array('PhabricatorTaskmasterDaemon'))
      ->setLimit(1)
      ->execute();
    if ($task_daemon) {
      $view->addItem(
        id(new PHUIStatusItemView())
          ->setIcon(PHUIStatusItemView::ICON_ACCEPT, 'green')
          ->setTarget(pht('Task Daemon Running')));
    } else {
      $view->addItem(
        id(new PHUIStatusItemView())
          ->setIcon(PHUIStatusItemView::ICON_WARNING, 'red')
          ->setTarget(pht('Task Daemon Not Running'))
          ->setNote($daemon_instructions));
    }


    if ($is_cluster) {
      // Just omit this status check for now in cluster environments. We
      // could make a service call and pull it from the repository host
      // eventually.
    } else if ($repository->usesLocalWorkingCopy()) {
      $local_parent = dirname($repository->getLocalPath());
      if (Filesystem::pathExists($local_parent)) {
        $view->addItem(
          id(new PHUIStatusItemView())
            ->setIcon(PHUIStatusItemView::ICON_ACCEPT, 'green')
            ->setTarget(pht('Storage Directory OK'))
            ->setNote(phutil_tag('tt', array(), $local_parent)));
      } else {
        $view->addItem(
          id(new PHUIStatusItemView())
            ->setIcon(PHUIStatusItemView::ICON_WARNING, 'red')
            ->setTarget(pht('No Storage Directory'))
            ->setNote(
              pht(
                'Storage directory %s does not exist, or is not readable by '.
                'the webserver. Create this directory or make it readable.',
                phutil_tag('tt', array(), $local_parent))));
        return $view;
      }

      $local_path = $repository->getLocalPath();
      $message = idx($messages, PhabricatorRepositoryStatusMessage::TYPE_INIT);
      if ($message) {
        switch ($message->getStatusCode()) {
          case PhabricatorRepositoryStatusMessage::CODE_ERROR:
            $view->addItem(
              id(new PHUIStatusItemView())
              ->setIcon(PHUIStatusItemView::ICON_WARNING, 'red')
                ->setTarget(pht('Initialization Error'))
                ->setNote($message->getParameter('message')));
            return $view;
          case PhabricatorRepositoryStatusMessage::CODE_OKAY:
              if (Filesystem::pathExists($local_path)) {
                $view->addItem(
                  id(new PHUIStatusItemView())
                    ->setIcon(PHUIStatusItemView::ICON_ACCEPT, 'green')
                    ->setTarget(pht('Working Copy OK'))
                    ->setNote(phutil_tag('tt', array(), $local_path)));
              } else {
                $view->addItem(
                  id(new PHUIStatusItemView())
                    ->setIcon(PHUIStatusItemView::ICON_WARNING, 'red')
                    ->setTarget(pht('Working Copy Error'))
                    ->setNote(
                      pht(
                        'Working copy %s has been deleted, or is not '.
                        'readable by the webserver. Make this directory '.
                        'readable. If it has been deleted, the daemons should '.
                        'restore it automatically.',
                        phutil_tag('tt', array(), $local_path))));
                return $view;
              }
            break;
          case PhabricatorRepositoryStatusMessage::CODE_WORKING:
            $view->addItem(
              id(new PHUIStatusItemView())
                ->setIcon(PHUIStatusItemView::ICON_CLOCK, 'green')
                ->setTarget(pht('Initializing Working Copy'))
                ->setNote(pht('Daemons are initializing the working copy.')));
            return $view;
          default:
            $view->addItem(
              id(new PHUIStatusItemView())
                ->setIcon(PHUIStatusItemView::ICON_WARNING, 'red')
                ->setTarget(pht('Unknown Init Status'))
                ->setNote($message->getStatusCode()));
            return $view;
        }
      } else {
        $view->addItem(
          id(new PHUIStatusItemView())
            ->setIcon(PHUIStatusItemView::ICON_CLOCK, 'orange')
            ->setTarget(pht('No Working Copy Yet'))
            ->setNote(
              pht('Waiting for daemons to build a working copy.')));
        return $view;
      }
    }

    $message = idx($messages, PhabricatorRepositoryStatusMessage::TYPE_FETCH);
    if ($message) {
      switch ($message->getStatusCode()) {
        case PhabricatorRepositoryStatusMessage::CODE_ERROR:
          $message = $message->getParameter('message');

          $suggestion = null;
          if (preg_match('/Permission denied \(publickey\)./', $message)) {
            $suggestion = pht(
              'Public Key Error: This error usually indicates that the '.
              'keypair you have configured does not have permission to '.
              'access the repository.');
          }

          $message = phutil_escape_html_newlines($message);

          if ($suggestion !== null) {
            $message = array(
              phutil_tag('strong', array(), $suggestion),
              phutil_tag('br'),
              phutil_tag('br'),
              phutil_tag('em', array(), pht('Raw Error')),
              phutil_tag('br'),
              $message,
            );
          }

          $view->addItem(
            id(new PHUIStatusItemView())
              ->setIcon(PHUIStatusItemView::ICON_WARNING, 'red')
              ->setTarget(pht('Update Error'))
              ->setNote($message));
          return $view;
        case PhabricatorRepositoryStatusMessage::CODE_OKAY:
          $ago = (PhabricatorTime::getNow() - $message->getEpoch());
          $view->addItem(
            id(new PHUIStatusItemView())
              ->setIcon(PHUIStatusItemView::ICON_ACCEPT, 'green')
              ->setTarget(pht('Updates OK'))
              ->setNote(
                pht(
                  'Last updated %s (%s ago).',
                  phabricator_datetime($message->getEpoch(), $viewer),
                  phutil_format_relative_time_detailed($ago))));
          break;
      }
    } else {
      $view->addItem(
        id(new PHUIStatusItemView())
          ->setIcon(PHUIStatusItemView::ICON_CLOCK, 'orange')
          ->setTarget(pht('Waiting For Update'))
          ->setNote(
            pht('Waiting for daemons to read updates.')));
    }

    if ($repository->isImporting()) {
      $progress = queryfx_all(
        $repository->establishConnection('r'),
        'SELECT importStatus, count(*) N FROM %T WHERE repositoryID = %d
          GROUP BY importStatus',
        id(new PhabricatorRepositoryCommit())->getTableName(),
        $repository->getID());

      $done = 0;
      $total = 0;
      foreach ($progress as $row) {
        $total += $row['N'] * 4;
        $status = $row['importStatus'];
        if ($status & PhabricatorRepositoryCommit::IMPORTED_MESSAGE) {
          $done += $row['N'];
        }
        if ($status & PhabricatorRepositoryCommit::IMPORTED_CHANGE) {
          $done += $row['N'];
        }
        if ($status & PhabricatorRepositoryCommit::IMPORTED_OWNERS) {
          $done += $row['N'];
        }
        if ($status & PhabricatorRepositoryCommit::IMPORTED_HERALD) {
          $done += $row['N'];
        }
      }

      if ($total) {
        $percentage = 100 * ($done / $total);
      } else {
        $percentage = 0;
      }

      // Cap this at "99.99%", because it's confusing to users when the actual
      // fraction is "99.996%" and it rounds up to "100.00%".
      if ($percentage > 99.99) {
        $percentage = 99.99;
      }

      $percentage = sprintf('%.2f%%', $percentage);

      $view->addItem(
        id(new PHUIStatusItemView())
          ->setIcon(PHUIStatusItemView::ICON_CLOCK, 'green')
          ->setTarget(pht('Importing'))
          ->setNote(
            pht('%s Complete', $percentage)));
    } else {
      $view->addItem(
        id(new PHUIStatusItemView())
          ->setIcon(PHUIStatusItemView::ICON_ACCEPT, 'green')
          ->setTarget(pht('Fully Imported')));
    }

    if (idx($messages, PhabricatorRepositoryStatusMessage::TYPE_NEEDS_UPDATE)) {
      $view->addItem(
        id(new PHUIStatusItemView())
          ->setIcon(PHUIStatusItemView::ICON_UP, 'indigo')
          ->setTarget(pht('Prioritized'))
          ->setNote(pht('This repository will be updated soon!')));
    }

    return $view;
  }

  private function buildRepositoryUpdateInterval(
    PhabricatorRepository $repository) {

    $smart_wait = $repository->loadUpdateInterval();

    $doc_href = PhabricatorEnv::getDoclink(
      'Diffusion User Guide: Repository Updates');

    return array(
      phutil_format_relative_time_detailed($smart_wait),
      " \xC2\xB7 ",
      phutil_tag(
        'a',
        array(
          'href' => $doc_href,
          'target' => '_blank',
        ),
        pht('Learn More')),
    );
  }


  private function buildMirrorActions(
    PhabricatorRepository $repository) {

    $viewer = $this->getRequest()->getUser();

    $mirror_actions = id(new PhabricatorActionListView())
      ->setObjectURI($this->getRequest()->getRequestURI())
      ->setUser($viewer);

    $new_mirror_uri = $this->getRepositoryControllerURI(
      $repository,
      'mirror/edit/');

    $mirror_actions->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Add Mirror'))
        ->setIcon('fa-plus')
        ->setHref($new_mirror_uri)
        ->setWorkflow(true));

    return $mirror_actions;
  }

  private function buildMirrorProperties(
    PhabricatorRepository $repository,
    PhabricatorActionListView $actions) {

    $viewer = $this->getRequest()->getUser();

    $mirror_properties = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setActionList($actions);

    $mirror_properties->addProperty(
      '',
      phutil_tag(
        'em',
        array(),
        pht('Automatically push changes into other remotes.')));

    return $mirror_properties;
  }

  private function buildMirrorList(
    PhabricatorRepository $repository,
    array $mirrors) {
    assert_instances_of($mirrors, 'PhabricatorRepositoryMirror');

    $mirror_list = id(new PHUIObjectItemListView())
      ->setNoDataString(pht('This repository has no configured mirrors.'));

    foreach ($mirrors as $mirror) {
      $item = id(new PHUIObjectItemView())
        ->setHeader($mirror->getRemoteURI());

      $edit_uri = $this->getRepositoryControllerURI(
        $repository,
        'mirror/edit/'.$mirror->getID().'/');

      $delete_uri = $this->getRepositoryControllerURI(
        $repository,
        'mirror/delete/'.$mirror->getID().'/');

      $item->addAction(
        id(new PHUIListItemView())
          ->setIcon('fa-pencil')
          ->setHref($edit_uri)
          ->setWorkflow(true));

      $item->addAction(
        id(new PHUIListItemView())
          ->setIcon('fa-times')
          ->setHref($delete_uri)
          ->setWorkflow(true));

      $mirror_list->addItem($item);
    }

    return $mirror_list;
  }

  private function getEnvConfigLink() {
    $config_href = '/config/edit/environment.append-paths/';
    return phutil_tag(
      'a',
      array(
        'href' => $config_href,
      ),
      'environment.append-paths');
  }

}
