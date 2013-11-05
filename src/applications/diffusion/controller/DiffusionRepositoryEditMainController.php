<?php

final class DiffusionRepositoryEditMainController
  extends DiffusionRepositoryEditController {

  public function processRequest() {
    $request = $this->getRequest();
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
      $header->setStatus('oh-ok', '', pht('Active'));
    } else {
      $header->setStatus('policy-noone', '', pht('Inactive'));
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

    $local_properties = null;
    if ($has_local) {
      $local_properties = $this->buildLocalProperties(
        $repository,
        $this->buildLocalActions($repository));
    }

    $actions_properties = $this->buildActionsProperties(
      $repository,
      $this->buildActionsActions($repository));

    $xactions = id(new PhabricatorRepositoryTransactionQuery())
      ->setViewer($viewer)
      ->withObjectPHIDs(array($repository->getPHID()))
      ->execute();

    $engine = id(new PhabricatorMarkupEngine())
      ->setViewer($viewer);
    foreach ($xactions as $xaction) {
      if ($xaction->getComment()) {
        $engine->addObject(
          $xaction->getComment(),
          PhabricatorApplicationTransactionComment::MARKUP_FIELD_COMMENT);
      }
    }
    $engine->process();

    $xaction_view = id(new PhabricatorApplicationTransactionView())
      ->setUser($viewer)
      ->setObjectPHID($repository->getPHID())
      ->setTransactions($xactions)
      ->setMarkupEngine($engine);

    $obj_box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($basic_properties)
      ->addPropertyList($policy_properties)
      ->addPropertyList($hosting_properties);

    if ($remote_properties) {
      $obj_box->addPropertyList($remote_properties);
    }

    if ($local_properties) {
      $obj_box->addPropertyList($local_properties);
    }

    $obj_box->addPropertyList($encoding_properties);

    if ($branches_properties) {
      $obj_box->addPropertyList($branches_properties);
    }

    if ($subversion_properties) {
      $obj_box->addPropertyList($subversion_properties);
    }

    $obj_box->addPropertyList($actions_properties);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $obj_box,
        $xaction_view,
      ),
      array(
        'title' => $title,
        'device' => true,
      ));
  }

  private function buildBasicActions(PhabricatorRepository $repository) {
    $viewer = $this->getRequest()->getUser();

    $view = id(new PhabricatorActionListView())
      ->setObjectURI($this->getRequest()->getRequestURI())
      ->setUser($viewer);

    $edit = id(new PhabricatorActionView())
      ->setIcon('edit')
      ->setName(pht('Edit Basic Information'))
      ->setHref($this->getRepositoryControllerURI($repository, 'edit/basic/'));
    $view->addAction($edit);

    $activate = id(new PhabricatorActionView())
      ->setHref(
        $this->getRepositoryControllerURI($repository, 'edit/activate/'))
      ->setWorkflow(true);

    if ($repository->isTracked()) {
      $activate
        ->setIcon('disable')
        ->setName(pht('Deactivate Repository'));
    } else {
      $activate
        ->setIcon('enable')
        ->setName(pht('Activate Repository'));
    }

    $view->addAction($activate);

    $view->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Delete Repository'))
        ->setIcon('delete')
        ->setHref(
          $this->getRepositoryControllerURI($repository, 'edit/delete/'))
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

    $view->addProperty(
      pht('Status'),
      $this->buildRepositoryStatus($repository));

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
      ->setIcon('edit')
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
      ->setActionList($actions)
      ->addSectionHeader(pht('Text Encoding'));

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
      ->setIcon('edit')
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
      ->setActionList($actions)
      ->addSectionHeader(pht('Policies'));

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
      ? $descriptions[DiffusionCapabilityPush::CAPABILITY]
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
      ->setIcon('edit')
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
      ->setActionList($actions)
      ->addSectionHeader(pht('Branches'));

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
      ->setIcon('edit')
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
      ->setActionList($actions)
      ->addSectionHeader(pht('Subversion'));

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
      ->setIcon('edit')
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
      ->setActionList($actions)
      ->addSectionHeader(pht('Actions'));

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
      ->setIcon('edit')
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
      ->setActionList($actions)
      ->addSectionHeader(pht('Remote'));

    $view->addProperty(
      pht('Remote URI'),
      $repository->getHumanReadableDetail('remote-uri'));

    return $view;
  }

  private function buildLocalActions(PhabricatorRepository $repository) {
    $viewer = $this->getRequest()->getUser();

    $view = id(new PhabricatorActionListView())
      ->setObjectURI($this->getRequest()->getRequestURI())
      ->setUser($viewer);

    $edit = id(new PhabricatorActionView())
      ->setIcon('edit')
      ->setName(pht('Edit Local'))
      ->setHref(
        $this->getRepositoryControllerURI($repository, 'edit/local/'));
    $view->addAction($edit);

    return $view;
  }

  private function buildLocalProperties(
    PhabricatorRepository $repository,
    PhabricatorActionListView $actions) {

    $viewer = $this->getRequest()->getUser();

    $view = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setActionList($actions)
      ->addSectionHeader(pht('Local'));

    $view->addProperty(
      pht('Local Path'),
      $repository->getHumanReadableDetail('local-path'));

    return $view;
  }

  private function buildHostingActions(PhabricatorRepository $repository) {
    $user = $this->getRequest()->getUser();

    $view = id(new PhabricatorActionListView())
      ->setObjectURI($this->getRequest()->getRequestURI())
      ->setUser($user);

    $edit = id(new PhabricatorActionView())
      ->setIcon('edit')
      ->setName(pht('Edit Hosting'))
      ->setHref(
        $this->getRepositoryControllerURI($repository, 'edit/hosting/'));
    $view->addAction($edit);

    return $view;
  }

  private function buildHostingProperties(
    PhabricatorRepository $repository,
    PhabricatorActionListView $actions) {

    $user = $this->getRequest()->getUser();

    $view = id(new PHUIPropertyListView())
      ->setUser($user)
      ->setActionList($actions)
      ->addSectionHeader(pht('Hosting'));

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

    return $view;
  }

  private function buildRepositoryStatus(
    PhabricatorRepository $repository) {

    $viewer = $this->getRequest()->getUser();

    $view = new PHUIStatusListView();

    $messages = id(new PhabricatorRepositoryStatusMessage())
      ->loadAllWhere('repositoryID = %d', $repository->getID());
    $messages = mpull($messages, null, 'getStatusType');

    if ($repository->isTracked()) {
      $view->addItem(
        id(new PHUIStatusItemView())
          ->setIcon('accept-green')
          ->setTarget(pht('Repository Active')));
    } else {
      $view->addItem(
        id(new PHUIStatusItemView())
          ->setIcon('warning')
          ->setTarget(pht('Repository Inactive'))
          ->setNote(
            pht('Activate this repository to begin or resume import.')));
      return $view;
    }

    $binaries = array();
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
            break;
          case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
            $binaries[] = 'hg';
            break;
        }
      }
    }

    $binaries = array_unique($binaries);
    foreach ($binaries as $binary) {
      $where = Filesystem::resolveBinary($binary);
      if (!$where) {
        $config_href = '/config/edit/environment.append-paths/';
        $config_link = phutil_tag(
          'a',
          array(
            'href' => $config_href,
          ),
          'environment.append-paths');

        $view->addItem(
          id(new PHUIStatusItemView())
            ->setIcon('warning-red')
            ->setTarget(
              pht('Missing Binary %s', phutil_tag('tt', array(), $binary)))
            ->setNote(pht(
              "Unable to find this binary in the webserver's PATH. You may ".
              "need to configure %s.",
              $config_link)));
      } else {
        $view->addItem(
          id(new PHUIStatusItemView())
            ->setIcon('accept-green')
            ->setTarget(
              pht('Found Binary %s', phutil_tag('tt', array(), $binary)))
            ->setNote(phutil_tag('tt', array(), $where)));
      }
    }

    $doc_href = PhabricatorEnv::getDocLink(
      'article/Managing_Daemons_with_phd.html');
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
      $view->addItem(
        id(new PHUIStatusItemView())
          ->setIcon('accept-green')
          ->setTarget(pht('Pull Daemon Running')));
    } else {
      $view->addItem(
        id(new PHUIStatusItemView())
          ->setIcon('warning-red')
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
          ->setIcon('accept-green')
          ->setTarget(pht('Task Daemon Running')));
    } else {
      $view->addItem(
        id(new PHUIStatusItemView())
          ->setIcon('warning-red')
          ->setTarget(pht('Task Daemon Not Running'))
          ->setNote($daemon_instructions));
    }

    if ($repository->usesLocalWorkingCopy()) {
      $local_parent = dirname($repository->getLocalPath());
      if (Filesystem::pathExists($local_parent)) {
        $view->addItem(
          id(new PHUIStatusItemView())
            ->setIcon('accept-green')
            ->setTarget(pht('Storage Directory OK'))
            ->setNote(phutil_tag('tt', array(), $local_parent)));
      } else {
        $view->addItem(
          id(new PHUIStatusItemView())
            ->setIcon('warning-red')
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
                ->setIcon('warning-red')
                ->setTarget(pht('Initialization Error'))
                ->setNote($message->getParameter('message')));
            return $view;
          case PhabricatorRepositoryStatusMessage::CODE_OKAY:
              if (Filesystem::pathExists($local_path)) {
                $view->addItem(
                  id(new PHUIStatusItemView())
                    ->setIcon('accept-green')
                    ->setTarget(pht('Working Copy OK'))
                    ->setNote(phutil_tag('tt', array(), $local_path)));
              } else {
                $view->addItem(
                  id(new PHUIStatusItemView())
                    ->setIcon('warning-red')
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
                ->setIcon('time-green')
                ->setTarget(pht('Initializing Working Copy'))
                ->setNote(pht('Daemons are initializing the working copy.')));
            return $view;
          default:
            $view->addItem(
              id(new PHUIStatusItemView())
                ->setIcon('warning-red')
                ->setTarget(pht('Unknown Init Status'))
                ->setNote($message->getStatusCode()));
            return $view;
        }
      } else {
        $view->addItem(
          id(new PHUIStatusItemView())
            ->setIcon('time-orange')
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
          $view->addItem(
            id(new PHUIStatusItemView())
              ->setIcon('warning-red')
              ->setTarget(pht('Update Error'))
              ->setNote($message->getParameter('message')));
          return $view;
        case PhabricatorRepositoryStatusMessage::CODE_OKAY:
          $view->addItem(
            id(new PHUIStatusItemView())
              ->setIcon('accept-green')
              ->setTarget(pht('Updates OK'))
              ->setNote(
                pht(
                  'Last updated %s.',
                  phabricator_datetime($message->getEpoch(), $viewer))));
          break;
      }
    } else {
      $view->addItem(
        id(new PHUIStatusItemView())
          ->setIcon('time-orange')
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

      $percentage = sprintf('%.1f%%', $percentage);

      $view->addItem(
        id(new PHUIStatusItemView())
          ->setIcon('time-green')
          ->setTarget(pht('Importing'))
          ->setNote(
            pht('%s Complete', $percentage)));
    } else {
      $view->addItem(
        id(new PHUIStatusItemView())
          ->setIcon('accept-green')
          ->setTarget(pht('Fully Imported')));
    }

    if (idx($messages, PhabricatorRepositoryStatusMessage::TYPE_NEEDS_UPDATE)) {
      $view->addItem(
        id(new PHUIStatusItemView())
          ->setIcon('up')
          ->setTarget(pht('Prioritized'))
          ->setNote(pht('This repository will be updated soon.')));
    }

    return $view;
  }

}
