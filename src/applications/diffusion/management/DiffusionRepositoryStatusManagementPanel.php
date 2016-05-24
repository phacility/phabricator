<?php

final class DiffusionRepositoryStatusManagementPanel
  extends DiffusionRepositoryManagementPanel {

  const PANELKEY = 'status';

  public function getManagementPanelLabel() {
    return pht('Status');
  }

  public function getManagementPanelOrder() {
    return 200;
  }

  public function getManagementPanelIcon() {
    $repository = $this->getRepository();

    // TODO: We could try to show a warning icon in more cases, but just
    // raise in the most serious cases for now.
    $messages = $this->loadStatusMessages($repository);

    $raw_error = $this->buildRepositoryRawError($repository, $messages);
    if ($raw_error) {
      return 'fa-exclamation-triangle red';
    }

    return 'fa-check grey';
  }

  protected function buildManagementPanelActions() {
    $repository = $this->getRepository();
    $viewer = $this->getViewer();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $repository,
      PhabricatorPolicyCapability::CAN_EDIT);

    $update_uri = $repository->getPathURI('edit/update/');

    return array(
      id(new PhabricatorActionView())
        ->setIcon('fa-refresh')
        ->setName(pht('Update Now'))
        ->setWorkflow(true)
        ->setDisabled(!$can_edit)
        ->setHref($update_uri),
    );
  }

  public function buildManagementPanelContent() {
    $repository = $this->getRepository();
    $viewer = $this->getViewer();

    $view = id(new PHUIPropertyListView())
      ->setViewer($viewer)
      ->setActionList($this->newActions());

    $view->addProperty(
      pht('Update Frequency'),
      $this->buildRepositoryUpdateInterval($repository));

    $messages = $this->loadStatusMessages($repository);

    $status = $this->buildRepositoryStatus($repository, $messages);
    $raw_error = $this->buildRepositoryRawError($repository, $messages);

    $view->addProperty(pht('Status'), $status);
    if ($raw_error) {
      $view->addSectionHeader(pht('Raw Error'));
      $view->addTextContent($raw_error);
    }

    return $this->newBox(pht('Status'), $view);
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

  private function buildRepositoryStatus(
    PhabricatorRepository $repository,
    array $messages) {

    $viewer = $this->getViewer();
    $is_cluster = $repository->getAlmanacServicePHID();

    $view = new PHUIStatusListView();

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
      $proto_https = PhabricatorRepositoryURI::BUILTIN_PROTOCOL_HTTPS;
      $proto_http = PhabricatorRepositoryURI::BUILTIN_PROTOCOL_HTTP;
      $can_http = $repository->canServeProtocol($proto_http, false) ||
                  $repository->canServeProtocol($proto_https, false);

      if ($can_http) {
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


      $proto_ssh = PhabricatorRepositoryURI::BUILTIN_PROTOCOL_SSH;
      $can_ssh = $repository->canServeProtocol($proto_ssh, false);

      if ($can_ssh) {
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
                  'Unable to find this binary in `%s`. '.
                  'You need to configure %s and include %s.',
                  'environment.append-paths',
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

          $view->addItem(
            id(new PHUIStatusItemView())
              ->setIcon(PHUIStatusItemView::ICON_WARNING, 'red')
              ->setTarget(pht('Update Error'))
              ->setNote($suggestion));
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
      $ratio = $repository->loadImportProgress();
      $percentage = sprintf('%.2f%%', 100 * $ratio);

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

  private function buildRepositoryRawError(
    PhabricatorRepository $repository,
    array $messages) {
    $viewer = $this->getViewer();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $repository,
      PhabricatorPolicyCapability::CAN_EDIT);

    $raw_error = null;

    $message = idx($messages, PhabricatorRepositoryStatusMessage::TYPE_FETCH);
    if ($message) {
      switch ($message->getStatusCode()) {
        case PhabricatorRepositoryStatusMessage::CODE_ERROR:
          $raw_error = $message->getParameter('message');
          break;
      }
    }

    if ($raw_error !== null) {
      if (!$can_edit) {
        $raw_message = pht(
          'You must be able to edit a repository to see raw error messages '.
          'because they sometimes disclose sensitive information.');
        $raw_message = phutil_tag('em', array(), $raw_message);
      } else {
        $raw_message = phutil_escape_html_newlines($raw_error);
      }
    } else {
      $raw_message = null;
    }

    return $raw_message;
  }

  private function loadStatusMessages(PhabricatorRepository $repository) {
    $messages = id(new PhabricatorRepositoryStatusMessage())
      ->loadAllWhere('repositoryID = %d', $repository->getID());
    $messages = mpull($messages, null, 'getStatusType');

    return $messages;
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
