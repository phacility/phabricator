<?php

final class DiffusionRepositoryCreateController
  extends DiffusionRepositoryEditController {

  private $edit;
  private $repository;

  protected function processDiffusionRequest(AphrontRequest $request) {
    $viewer = $request->getUser();
    $this->edit = $request->getURIData('edit');

    // NOTE: We can end up here via either "Create Repository", or via
    // "Import Repository", or via "Edit Remote", or via "Edit Policies". In
    // the latter two cases, we show only a few of the pages.

    $repository = null;
    $service = null;
    switch ($this->edit) {
      case 'remote':
      case 'policy':
        $repository = $this->getDiffusionRequest()->getRepository();

        // Make sure we have CAN_EDIT.
        PhabricatorPolicyFilter::requireCapability(
          $viewer,
          $repository,
          PhabricatorPolicyCapability::CAN_EDIT);

        $this->setRepository($repository);

        $cancel_uri = $this->getRepositoryControllerURI($repository, 'edit/');
        break;
      case 'import':
      case 'create':
        $this->requireApplicationCapability(
          DiffusionCreateRepositoriesCapability::CAPABILITY);

        // Pick a random open service to allocate this repository on, if any
        // exist. If there are no services, we aren't in cluster mode and
        // will allocate locally. If there are services but none permit
        // allocations, we fail.
        $services = id(new AlmanacServiceQuery())
          ->setViewer(PhabricatorUser::getOmnipotentUser())
          ->withServiceClasses(
            array(
              'AlmanacClusterRepositoryServiceType',
            ))
          ->execute();
        if ($services) {
          // Filter out services which do not permit new allocations.
          foreach ($services as $key => $possible_service) {
            if ($possible_service->getAlmanacPropertyValue('closed')) {
              unset($services[$key]);
            }
          }

          if (!$services) {
            throw new Exception(
              pht(
                'This install is configured in cluster mode, but all '.
                'available repository cluster services are closed to new '.
                'allocations. At least one service must be open to allow '.
                'new allocations to take place.'));
          }

          shuffle($services);
          $service = head($services);
        }

        $cancel_uri = $this->getApplicationURI('new/');
        break;
      default:
        throw new Exception(pht('Invalid edit operation!'));
    }

    $form = id(new PHUIPagedFormView())
      ->setUser($viewer)
      ->setCancelURI($cancel_uri);

    switch ($this->edit) {
      case 'remote':
        $title = pht('Edit Remote');
        $form
          ->addPage('remote-uri', $this->buildRemoteURIPage())
          ->addPage('auth', $this->buildAuthPage());
        break;
      case 'policy':
        $title = pht('Edit Policies');
        $form
          ->addPage('policy', $this->buildPolicyPage());
        break;
      case 'create':
        $title = pht('Create Repository');
        $form
          ->addPage('vcs', $this->buildVCSPage())
          ->addPage('name', $this->buildNamePage())
          ->addPage('policy', $this->buildPolicyPage())
          ->addPage('done', $this->buildDonePage());
        break;
      case 'import':
        $title = pht('Import Repository');
        $form
          ->addPage('vcs', $this->buildVCSPage())
          ->addPage('name', $this->buildNamePage())
          ->addPage('remote-uri', $this->buildRemoteURIPage())
          ->addPage('auth', $this->buildAuthPage())
          ->addPage('policy', $this->buildPolicyPage())
          ->addPage('done', $this->buildDonePage());
        break;
    }

    if ($request->isFormPost()) {
      $form->readFromRequest($request);
      if ($form->isComplete()) {

        $is_create = ($this->edit === 'import' || $this->edit === 'create');
        $is_auth = ($this->edit == 'import' || $this->edit == 'remote');
        $is_policy = ($this->edit != 'remote');
        $is_init = ($this->edit == 'create');

        if ($is_create) {
          $repository = PhabricatorRepository::initializeNewRepository(
            $viewer);
        }

        $template = id(new PhabricatorRepositoryTransaction());

        $type_name = PhabricatorRepositoryTransaction::TYPE_NAME;
        $type_vcs = PhabricatorRepositoryTransaction::TYPE_VCS;
        $type_activate = PhabricatorRepositoryTransaction::TYPE_ACTIVATE;
        $type_local_path = PhabricatorRepositoryTransaction::TYPE_LOCAL_PATH;
        $type_remote_uri = PhabricatorRepositoryTransaction::TYPE_REMOTE_URI;
        $type_hosting = PhabricatorRepositoryTransaction::TYPE_HOSTING;
        $type_http = PhabricatorRepositoryTransaction::TYPE_PROTOCOL_HTTP;
        $type_ssh = PhabricatorRepositoryTransaction::TYPE_PROTOCOL_SSH;
        $type_credential = PhabricatorRepositoryTransaction::TYPE_CREDENTIAL;
        $type_view = PhabricatorTransactions::TYPE_VIEW_POLICY;
        $type_edit = PhabricatorTransactions::TYPE_EDIT_POLICY;
        $type_push = PhabricatorRepositoryTransaction::TYPE_PUSH_POLICY;
        $type_service = PhabricatorRepositoryTransaction::TYPE_SERVICE;

        $xactions = array();

        // If we're creating a new repository, set all this core stuff.
        if ($is_create) {
          $callsign = $form->getPage('name')
            ->getControl('callsign')->getValue();

          // We must set this to a unique value to save the repository
          // initially, and it's immutable, so we don't bother using
          // transactions to apply this change.
          $repository->setCallsign($callsign);

          $xactions[] = id(clone $template)
            ->setTransactionType($type_name)
            ->setNewValue(
              $form->getPage('name')->getControl('name')->getValue());

          $xactions[] = id(clone $template)
            ->setTransactionType($type_vcs)
            ->setNewValue(
              $form->getPage('vcs')->getControl('vcs')->getValue());

          $activate = $form->getPage('done')
            ->getControl('activate')->getValue();
          $xactions[] = id(clone $template)
            ->setTransactionType($type_activate)
            ->setNewValue(($activate == 'start'));

          if ($service) {
            $xactions[] = id(clone $template)
              ->setTransactionType($type_service)
              ->setNewValue($service->getPHID());
          }

          $default_local_path = PhabricatorEnv::getEnvConfig(
            'repository.default-local-path');

          $default_local_path = rtrim($default_local_path, '/');
          $default_local_path = $default_local_path.'/'.$callsign.'/';

          $xactions[] = id(clone $template)
            ->setTransactionType($type_local_path)
            ->setNewValue($default_local_path);
        }

        if ($is_init) {
          $xactions[] = id(clone $template)
            ->setTransactionType($type_hosting)
            ->setNewValue(true);
          $vcs = $form->getPage('vcs')->getControl('vcs')->getValue();
          if ($vcs != PhabricatorRepositoryType::REPOSITORY_TYPE_SVN) {
            if (PhabricatorEnv::getEnvConfig('diffusion.allow-http-auth')) {
              $v_http_mode = PhabricatorRepository::SERVE_READWRITE;
            } else {
              $v_http_mode = PhabricatorRepository::SERVE_OFF;
            }
            $xactions[] = id(clone $template)
              ->setTransactionType($type_http)
              ->setNewValue($v_http_mode);
          }

          if (PhabricatorEnv::getEnvConfig('diffusion.ssh-user')) {
            $v_ssh_mode = PhabricatorRepository::SERVE_READWRITE;
          } else {
            $v_ssh_mode = PhabricatorRepository::SERVE_OFF;
          }
          $xactions[] = id(clone $template)
            ->setTransactionType($type_ssh)
            ->setNewValue($v_ssh_mode);
        }

        if ($is_auth) {
          $xactions[] = id(clone $template)
            ->setTransactionType($type_remote_uri)
            ->setNewValue(
              $form->getPage('remote-uri')->getControl('remoteURI')
                ->getValue());

          $xactions[] = id(clone $template)
            ->setTransactionType($type_credential)
            ->setNewValue(
              $form->getPage('auth')->getControl('credential')->getValue());
        }

        if ($is_policy) {
          $xactions[] = id(clone $template)
            ->setTransactionType($type_view)
            ->setNewValue(
              $form->getPage('policy')->getControl('viewPolicy')->getValue());

          $xactions[] = id(clone $template)
            ->setTransactionType($type_edit)
            ->setNewValue(
              $form->getPage('policy')->getControl('editPolicy')->getValue());

          if ($is_init || $repository->isHosted()) {
            $xactions[] = id(clone $template)
              ->setTransactionType($type_push)
              ->setNewValue(
                $form->getPage('policy')->getControl('pushPolicy')->getValue());
          }
        }

        id(new PhabricatorRepositoryEditor())
          ->setContinueOnNoEffect(true)
          ->setContentSourceFromRequest($request)
          ->setActor($viewer)
          ->applyTransactions($repository, $xactions);

        $repo_uri = $this->getRepositoryControllerURI($repository, 'edit/');
        return id(new AphrontRedirectResponse())->setURI($repo_uri);
      }
    } else {
      $dict = array();
      if ($repository) {
        $dict = array(
          'remoteURI' => $repository->getRemoteURI(),
          'credential' => $repository->getCredentialPHID(),
          'viewPolicy' => $repository->getViewPolicy(),
          'editPolicy' => $repository->getEditPolicy(),
          'pushPolicy' => $repository->getPushPolicy(),
        );
      }
      $form->readFromObject($dict);
    }

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($title);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $form,
      ),
      array(
        'title' => $title,
      ));
  }


/* -(  Page: VCS Type  )----------------------------------------------------- */


  private function buildVCSPage() {

    $is_import = ($this->edit == 'import');

    if ($is_import) {
      $git_str = pht(
        'Import a Git repository (for example, a repository hosted '.
        'on GitHub).');
      $hg_str = pht(
        'Import a Mercurial repository (for example, a repository '.
        'hosted on Bitbucket).');
      $svn_str = pht('Import a Subversion repository.');
    } else {
      $git_str = pht('Create a new, empty Git repository.');
      $hg_str = pht('Create a new, empty Mercurial repository.');
      $svn_str = pht('Create a new, empty Subversion repository.');
    }

    $control = id(new AphrontFormRadioButtonControl())
      ->setName('vcs')
      ->setLabel(pht('Type'))
      ->addButton(
        PhabricatorRepositoryType::REPOSITORY_TYPE_GIT,
        pht('Git'),
        $git_str)
      ->addButton(
        PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL,
        pht('Mercurial'),
        $hg_str)
      ->addButton(
        PhabricatorRepositoryType::REPOSITORY_TYPE_SVN,
        pht('Subversion'),
        $svn_str);

    if ($is_import) {
      $control->addButton(
        PhabricatorRepositoryType::REPOSITORY_TYPE_PERFORCE,
        pht('Perforce'),
        pht(
          'Perforce is not directly supported, but you can import '.
          'a Perforce repository as a Git repository using %s.',
          phutil_tag(
            'a',
            array(
              'href' =>
                'http://www.perforce.com/product/components/git-fusion',
              'target' => '_blank',
            ),
            pht('Perforce Git Fusion'))),
        'disabled',
        $disabled = true);
    }

    return id(new PHUIFormPageView())
      ->setPageName(pht('Repository Type'))
      ->setUser($this->getRequest()->getUser())
      ->setValidateFormPageCallback(array($this, 'validateVCSPage'))
      ->addControl($control);
  }

  public function validateVCSPage(PHUIFormPageView $page) {
    $valid = array(
      PhabricatorRepositoryType::REPOSITORY_TYPE_GIT => true,
      PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL => true,
      PhabricatorRepositoryType::REPOSITORY_TYPE_SVN => true,
    );

    $c_vcs = $page->getControl('vcs');
    $v_vcs = $c_vcs->getValue();
    if (!$v_vcs) {
      $c_vcs->setError(pht('Required'));
      $page->addPageError(
        pht('You must select a version control system.'));
    } else if (empty($valid[$v_vcs])) {
      $c_vcs->setError(pht('Invalid'));
      $page->addPageError(
        pht('You must select a valid version control system.'));
    }

    return $c_vcs->isValid();
  }


/* -(  Page: Name and Callsign  )-------------------------------------------- */


  private function buildNamePage() {
    return id(new PHUIFormPageView())
      ->setUser($this->getRequest()->getUser())
      ->setPageName(pht('Repository Name and Location'))
      ->setValidateFormPageCallback(array($this, 'validateNamePage'))
      ->addRemarkupInstructions(
        pht(
          '**Choose a human-readable name for this repository**, like '.
          '"CompanyName Mobile App" or "CompanyName Backend Server". You '.
          'can change this later.'))
      ->addControl(
        id(new AphrontFormTextControl())
          ->setName('name')
          ->setLabel(pht('Name'))
          ->setCaption(pht('Human-readable repository name.')))
      ->addRemarkupInstructions(
        pht(
          '**Choose a "Callsign" for the repository.** This is a short, '.
          'unique string which identifies commits elsewhere in Phabricator. '.
          'For example, you might use `M` for your mobile app repository '.
          'and `B` for your backend repository.'.
          "\n\n".
          '**Callsigns must be UPPERCASE**, and can not be edited after the '.
          'repository is created. Generally, you should choose short '.
          'callsigns.'))
      ->addControl(
        id(new AphrontFormTextControl())
          ->setName('callsign')
          ->setLabel(pht('Callsign'))
          ->setCaption(pht('Short UPPERCASE identifier.')));
  }

  public function validateNamePage(PHUIFormPageView $page) {
    $c_name = $page->getControl('name');
    $v_name = $c_name->getValue();
    if (!strlen($v_name)) {
      $c_name->setError(pht('Required'));
      $page->addPageError(
        pht('You must choose a name for this repository.'));
    }

    $c_call = $page->getControl('callsign');
    $v_call = $c_call->getValue();
    if (!strlen($v_call)) {
      $c_call->setError(pht('Required'));
      $page->addPageError(
        pht('You must choose a callsign for this repository.'));
    } else if (!preg_match('/^[A-Z]+\z/', $v_call)) {
      $c_call->setError(pht('Invalid'));
      $page->addPageError(
        pht('The callsign must contain only UPPERCASE letters.'));
    } else {
      $exists = false;
      try {
        $repo = id(new PhabricatorRepositoryQuery())
          ->setViewer($this->getRequest()->getUser())
          ->withCallsigns(array($v_call))
          ->executeOne();
        $exists = (bool)$repo;
      } catch (PhabricatorPolicyException $ex) {
        $exists = true;
      }
      if ($exists) {
        $c_call->setError(pht('Not Unique'));
        $page->addPageError(
          pht(
            'Another repository already uses that callsign. You must choose '.
            'a unique callsign.'));
      }
    }

    return $c_name->isValid() &&
           $c_call->isValid();
  }


/* -(  Page: Remote URI  )--------------------------------------------------- */


  private function buildRemoteURIPage() {
    return id(new PHUIFormPageView())
      ->setUser($this->getRequest()->getUser())
      ->setPageName(pht('Repository Remote URI'))
      ->setValidateFormPageCallback(array($this, 'validateRemoteURIPage'))
      ->setAdjustFormPageCallback(array($this, 'adjustRemoteURIPage'))
      ->addControl(
        id(new AphrontFormTextControl())
          ->setName('remoteURI'));
  }

  public function adjustRemoteURIPage(PHUIFormPageView $page) {
    $form = $page->getForm();

    $is_git = false;
    $is_svn = false;
    $is_mercurial = false;

    if ($this->getRepository()) {
      $vcs = $this->getRepository()->getVersionControlSystem();
    } else {
      $vcs = $form->getPage('vcs')->getControl('vcs')->getValue();
    }

    switch ($vcs) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
        $is_git = true;
        break;
      case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
        $is_svn = true;
        break;
      case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
        $is_mercurial = true;
        break;
      default:
        throw new Exception(pht('Unsupported VCS!'));
    }

    $has_local = ($is_git || $is_mercurial);
    if ($is_git) {
      $uri_label = pht('Remote URI');
      $instructions = pht(
        'Enter the URI to clone this Git repository from. It should usually '.
        'look like one of these examples:'.
        "\n\n".
        "| Example Git Remote URIs |\n".
        "| ----------------------- |\n".
        "| `git@github.com:example/example.git` |\n".
        "| `ssh://user@host.com/git/example.git` |\n".
        "| `https://example.com/repository.git` |\n");
    } else if ($is_mercurial) {
      $uri_label = pht('Remote URI');
      $instructions = pht(
        'Enter the URI to clone this Mercurial repository from. It should '.
        'usually look like one of these examples:'.
        "\n\n".
        "| Example Mercurial Remote URIs |\n".
        "| ----------------------- |\n".
        "| `ssh://hg@bitbucket.org/example/repository` |\n".
        "| `https://bitbucket.org/example/repository` |\n");
    } else if ($is_svn) {
      $uri_label = pht('Repository Root');
      $instructions = pht(
        'Enter the **Repository Root** for this Subversion repository. '.
        'You can figure this out by running `svn info` in a working copy '.
        'and looking at the value in the `Repository Root` field. It '.
        'should be a URI and will usually look like these:'.
        "\n\n".
        "| Example Subversion Repository Root URIs |\n".
        "| ------------------------------ |\n".
        "| `http://svn.example.org/svnroot/` |\n".
        "| `svn+ssh://svn.example.com/svnroot/` |\n".
        "| `svn://svn.example.net/svnroot/` |\n".
        "\n\n".
        "You **MUST** specify the root of the repository, not a ".
        "subdirectory. (If you want to import only part of a Subversion ".
        "repository, use the //Import Only// option at the end of this ".
        "workflow.)");
    } else {
      throw new Exception(pht('Unsupported VCS!'));
    }

    $page->addRemarkupInstructions($instructions, 'remoteURI');
    $page->getControl('remoteURI')->setLabel($uri_label);
  }

  public function validateRemoteURIPage(PHUIFormPageView $page) {
    $c_remote = $page->getControl('remoteURI');
    $v_remote = $c_remote->getValue();

    if (!strlen($v_remote)) {
      $c_remote->setError(pht('Required'));
      $page->addPageError(
        pht('You must specify a URI.'));
    } else {
      try {
        PhabricatorRepository::assertValidRemoteURI($v_remote);
      } catch (Exception $ex) {
        $c_remote->setError(pht('Invalid'));
        $page->addPageError($ex->getMessage());
      }
    }

    return $c_remote->isValid();
  }


/* -(  Page: Authentication  )----------------------------------------------- */


  public function buildAuthPage() {
    return id(new PHUIFormPageView())
      ->setPageName(pht('Authentication'))
      ->setUser($this->getRequest()->getUser())
      ->setAdjustFormPageCallback(array($this, 'adjustAuthPage'))
      ->addControl(
        id(new PassphraseCredentialControl())
          ->setName('credential'));
  }


  public function adjustAuthPage($page) {
    $form = $page->getForm();

    if ($this->getRepository()) {
      $vcs = $this->getRepository()->getVersionControlSystem();
    } else {
      $vcs = $form->getPage('vcs')->getControl('vcs')->getValue();
    }

    $remote_uri = $form->getPage('remote-uri')
      ->getControl('remoteURI')
      ->getValue();

    $proto = PhabricatorRepository::getRemoteURIProtocol($remote_uri);
    $remote_user = $this->getRemoteURIUser($remote_uri);

    $c_credential = $page->getControl('credential');
    $c_credential->setDefaultUsername($remote_user);

    if ($this->isSSHProtocol($proto)) {
      $c_credential->setLabel(pht('SSH Key'));
      $c_credential->setCredentialType(
        PassphraseSSHPrivateKeyTextCredentialType::CREDENTIAL_TYPE);
      $provides_type = PassphraseSSHPrivateKeyCredentialType::PROVIDES_TYPE;

      $page->addRemarkupInstructions(
        pht(
          'Choose or add the SSH credentials to use to connect to the the '.
          'repository hosted at:'.
          "\n\n".
          "  lang=text\n".
          "  %s",
          $remote_uri),
        'credential');
    } else if ($this->isUsernamePasswordProtocol($proto)) {
      $c_credential->setLabel(pht('Password'));
      $c_credential->setAllowNull(true);
      $c_credential->setCredentialType(
        PassphrasePasswordCredentialType::CREDENTIAL_TYPE);
      $provides_type = PassphrasePasswordCredentialType::PROVIDES_TYPE;

      $page->addRemarkupInstructions(
        pht(
          'Choose the username and password used to connect to the '.
          'repository hosted at:'.
          "\n\n".
          "  lang=text\n".
          "  %s".
          "\n\n".
          "If this repository does not require a username or password, ".
          "you can continue to the next step.",
          $remote_uri),
        'credential');
    } else {
      throw new Exception(pht('Unknown URI protocol!'));
    }

    if ($provides_type) {
      $viewer = $this->getRequest()->getUser();

      $options = id(new PassphraseCredentialQuery())
        ->setViewer($viewer)
        ->withIsDestroyed(false)
        ->withProvidesTypes(array($provides_type))
        ->execute();

      $c_credential->setOptions($options);
    }

  }

  public function validateAuthPage(PHUIFormPageView $page) {
    $form = $page->getForm();
    $remote_uri = $form->getPage('remote')->getControl('remoteURI')->getValue();
    $proto = $this->getRemoteURIProtocol($remote_uri);

    $c_credential = $page->getControl('credential');
    $v_credential = $c_credential->getValue();

    // NOTE: We're using the omnipotent user here because the viewer might be
    // editing a repository they're allowed to edit which uses a credential they
    // are not allowed to see. This is fine, as long as they don't change it.
    $credential = id(new PassphraseCredentialQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withPHIDs(array($v_credential))
      ->executeOne();

    if ($this->isSSHProtocol($proto)) {
      if (!$credential) {
        $c_credential->setError(pht('Required'));
        $page->addPageError(
          pht('You must choose an SSH credential to connect over SSH.'));
      }

      $ssh_type = PassphraseSSHPrivateKeyCredentialType::PROVIDES_TYPE;
      if ($credential->getProvidesType() !== $ssh_type) {
        $c_credential->setError(pht('Invalid'));
        $page->addPageError(
          pht(
            'You must choose an SSH credential, not some other type '.
            'of credential.'));
      }

    } else if ($this->isUsernamePasswordProtocol($proto)) {
      if ($credential) {
        $password_type = PassphrasePasswordCredentialType::PROVIDES_TYPE;
        if ($credential->getProvidesType() !== $password_type) {
        $c_credential->setError(pht('Invalid'));
        $page->addPageError(
          pht(
            'You must choose a username/password credential, not some other '.
            'type of credential.'));
        }
      }

      return $c_credential->isValid();
    } else {
      return true;
    }
  }


/* -(  Page: Policy  )------------------------------------------------------- */


  private function buildPolicyPage() {
    $viewer = $this->getRequest()->getUser();
    if ($this->getRepository()) {
      $repository = $this->getRepository();
    } else {
      $repository = PhabricatorRepository::initializeNewRepository($viewer);
    }

    $policies = id(new PhabricatorPolicyQuery())
      ->setViewer($viewer)
      ->setObject($repository)
      ->execute();

    $view_policy = id(new AphrontFormPolicyControl())
      ->setUser($viewer)
      ->setCapability(PhabricatorPolicyCapability::CAN_VIEW)
      ->setPolicyObject($repository)
      ->setPolicies($policies)
      ->setName('viewPolicy');

    $edit_policy = id(new AphrontFormPolicyControl())
      ->setUser($viewer)
      ->setCapability(PhabricatorPolicyCapability::CAN_EDIT)
      ->setPolicyObject($repository)
      ->setPolicies($policies)
      ->setName('editPolicy');

    $push_policy = id(new AphrontFormPolicyControl())
      ->setUser($viewer)
      ->setCapability(DiffusionPushCapability::CAPABILITY)
      ->setPolicyObject($repository)
      ->setPolicies($policies)
      ->setName('pushPolicy');

    return id(new PHUIFormPageView())
        ->setPageName(pht('Policies'))
        ->setValidateFormPageCallback(array($this, 'validatePolicyPage'))
        ->setAdjustFormPageCallback(array($this, 'adjustPolicyPage'))
        ->setUser($viewer)
        ->addRemarkupInstructions(
          pht('Select access policies for this repository.'))
        ->addControl($view_policy)
        ->addControl($edit_policy)
        ->addControl($push_policy);
  }

  public function adjustPolicyPage(PHUIFormPageView $page) {
    if ($this->getRepository()) {
      $repository = $this->getRepository();
      $show_push = $repository->isHosted();
    } else {
      $show_push = ($this->edit == 'create');
    }

    if (!$show_push) {
      $c_push = $page->getControl('pushPolicy');
      $c_push->setHidden(true);
    }
  }

  public function validatePolicyPage(PHUIFormPageView $page) {
    $form = $page->getForm();
    $viewer = $this->getRequest()->getUser();

    $c_view = $page->getControl('viewPolicy');
    $c_edit = $page->getControl('editPolicy');
    $c_push = $page->getControl('pushPolicy');
    $v_view = $c_view->getValue();
    $v_edit = $c_edit->getValue();
    $v_push = $c_push->getValue();

    if ($this->getRepository()) {
      $repository = $this->getRepository();
    } else {
      $repository = PhabricatorRepository::initializeNewRepository($viewer);
    }

    $proxy = clone $repository;
    $proxy->setViewPolicy($v_view);
    $proxy->setEditPolicy($v_edit);

    $can_view = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $proxy,
      PhabricatorPolicyCapability::CAN_VIEW);

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $proxy,
      PhabricatorPolicyCapability::CAN_EDIT);

    if (!$can_view) {
      $c_view->setError(pht('Invalid'));
      $page->addPageError(
        pht(
          'You can not use the selected policy, because you would be unable '.
          'to see the repository.'));
    }

    if (!$can_edit) {
      $c_edit->setError(pht('Invalid'));
      $page->addPageError(
        pht(
          'You can not use the selected edit policy, because you would be '.
          'unable to edit the repository.'));
    }

    return $c_view->isValid() &&
           $c_edit->isValid();
  }

/* -(  Page: Done  )--------------------------------------------------------- */


  private function buildDonePage() {

    $is_create = ($this->edit == 'create');
    if ($is_create) {
      $now_label = pht('Create Repository Now');
      $now_caption = pht(
        'Create the repository right away. This will create the repository '.
        'using default settings.');

      $wait_label = pht('Configure More Options First');
      $wait_caption = pht(
        'Configure more options before creating the repository. '.
        'This will let you fine-tune settings. You can create the repository '.
        'whenever you are ready.');
    } else {
      $now_label = pht('Start Import Now');
      $now_caption = pht(
        'Start importing the repository right away. This will import '.
        'the entire repository using default settings.');

      $wait_label = pht('Configure More Options First');
      $wait_caption = pht(
        'Configure more options before beginning the repository '.
        'import. This will let you fine-tune settings. You can '.
        'start the import whenever you are ready.');
    }

    return id(new PHUIFormPageView())
      ->setPageName(pht('Repository Ready!'))
      ->setValidateFormPageCallback(array($this, 'validateDonePage'))
      ->setUser($this->getRequest()->getUser())
      ->addControl(
        id(new AphrontFormRadioButtonControl())
          ->setName('activate')
          ->setLabel(pht('Start Now'))
          ->addButton(
            'start',
            $now_label,
            $now_caption)
          ->addButton(
            'wait',
            $wait_label,
            $wait_caption));
  }

  public function validateDonePage(PHUIFormPageView $page) {
    $c_activate = $page->getControl('activate');
    $v_activate = $c_activate->getValue();

    if ($v_activate != 'start' && $v_activate != 'wait') {
      $c_activate->setError(pht('Required'));
      $page->addPageError(
        pht('Make a choice about repository activation.'));
    }

    return $c_activate->isValid();
  }


/* -(  Internal  )----------------------------------------------------------- */

  private function getRemoteURIUser($raw_uri) {
    $uri = new PhutilURI($raw_uri);
    if ($uri->getUser()) {
      return $uri->getUser();
    }

    $git_uri = new PhutilGitURI($raw_uri);
    if (strlen($git_uri->getDomain()) && strlen($git_uri->getPath())) {
      return $git_uri->getUser();
    }

    return null;
  }

  private function isSSHProtocol($proto) {
    return ($proto == 'git' || $proto == 'ssh' || $proto == 'svn+ssh');
  }

  private function isUsernamePasswordProtocol($proto) {
    return ($proto == 'http' || $proto == 'https' || $proto == 'svn');
  }

  private function setRepository(PhabricatorRepository $repository) {
    $this->repository = $repository;
    return $this;
  }

  private function getRepository() {
    return $this->repository;
  }

}
