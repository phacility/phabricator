<?php

final class DiffusionRepositoryCreateController
  extends DiffusionRepositoryEditController {

  private $edit;
  private $repository;

  public function willProcessRequest(array $data) {
    parent::willProcessRequest($data);
    $this->edit = $data['edit'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    // NOTE: We can end up here via either "Create Repository", or via
    // "Import Repository", or via "Edit Remote". In the latter case, we show
    // only a few of the pages.

    $repository = null;
    switch ($this->edit) {
      case 'remote':
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
          DiffusionCapabilityCreateRepositories::CAPABILITY);

        $cancel_uri = $this->getApplicationURI('new/');
        break;
      default:
        throw new Exception("Invalid edit operation!");
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
      case 'create':
        $title = pht('Create Repository');
        $form
          ->addPage('vcs', $this->buildVCSPage())
          ->addPage('name', $this->buildNamePage())
          ->addPage('done', $this->buildDonePage());
        break;
      case 'import':
        $title = pht('Import Repository');
        $form
          ->addPage('vcs', $this->buildVCSPage())
          ->addPage('name', $this->buildNamePage())
          ->addPage('remote-uri', $this->buildRemoteURIPage())
          ->addPage('auth', $this->buildAuthPage())
          ->addPage('done', $this->buildDonePage());
        break;
    }

    if ($request->isFormPost()) {
      $form->readFromRequest($request);
      if ($form->isComplete()) {

        $is_create = ($this->edit === 'import' || $this->edit === 'create');
        $is_auth = ($this->edit == 'import' || $this->edit == 'remote');
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
        $type_ssh_login = PhabricatorRepositoryTransaction::TYPE_SSH_LOGIN;
        $type_ssh_key = PhabricatorRepositoryTransaction::TYPE_SSH_KEY;
        $type_ssh_keyfile = PhabricatorRepositoryTransaction::TYPE_SSH_KEYFILE;
        $type_http_login = PhabricatorRepositoryTransaction::TYPE_HTTP_LOGIN;
        $type_http_pass = PhabricatorRepositoryTransaction::TYPE_HTTP_PASS;
        $type_hosting = PhabricatorRepositoryTransaction::TYPE_HOSTING;

        $xactions = array();

        // If we're creating a new repository, set all this core stuff.
        if ($is_create) {
          $callsign = $form->getPage('name')
            ->getControl('callsign')->getValue();

          // We must set this to a unique value to save the repository
          // initially, and it's immutable, so we don't bother using
          // transactions to apply this change.
          $repository->setCallsign($callsign);

          // Put the repository in "Importing" mode until we finish
          // parsing it.
          $repository->setDetail('importing', true);

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
            ->setNewValue(
              ($activate == 'start'));

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
        }

        if ($is_auth) {
          $xactions[] = id(clone $template)
            ->setTransactionType($type_remote_uri)
            ->setNewValue(
              $form->getPage('remote-uri')->getControl('remoteURI')
                ->getValue());

          $xactions[] = id(clone $template)
            ->setTransactionType($type_ssh_login)
            ->setNewValue(
              $form->getPage('auth')->getControl('ssh-login')->getValue());

          $xactions[] = id(clone $template)
            ->setTransactionType($type_ssh_key)
            ->setNewValue(
              $form->getPage('auth')->getControl('ssh-key')->getValue());

          $xactions[] = id(clone $template)
            ->setTransactionType($type_ssh_keyfile)
            ->setNewValue(
              $form->getPage('auth')->getControl('ssh-keyfile')->getValue());

          $xactions[] = id(clone $template)
            ->setTransactionType($type_http_login)
            ->setNewValue(
              $form->getPage('auth')->getControl('http-login')->getValue());

          $xactions[] = id(clone $template)
            ->setTransactionType($type_http_pass)
            ->setNewValue(
              $form->getPage('auth')->getControl('http-pass')->getValue());
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
          'ssh-login' => $repository->getDetail('ssh-login'),
          'ssh-key' => $repository->getDetail('ssh-key'),
          'ssh-keyfile' => $repository->getDetail('ssh-keyfile'),
          'http-login' => $repository->getDetail('http-login'),
          'http-pass' => $repository->getDetail('http-pass'),
        );
      }
      $form->readFromObject($dict);
    }

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName($title));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $form,
      ),
      array(
        'title' => $title,
        'device' => true,
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
    } else if (!preg_match('/^[A-Z]+$/', $v_call)) {
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
        throw new Exception("Unsupported VCS!");
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
        "| `file:///local/path/to/repo` |\n".
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
        "| `file:///local/path/to/repo` |\n");
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
        "| `file:///local/path/to/svnroot/` |\n".
        "\n\n".
        "Make sure you specify the root of the repository, not a ".
        "subdirectory.");
    } else {
      throw new Exception("Unsupported VCS!");
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
        pht("You must specify a URI."));
    } else {
      $proto = $this->getRemoteURIProtocol($v_remote);

      if ($proto === 'file') {
        if (!preg_match('@^file:///@', $v_remote)) {
          $c_remote->setError(pht('Invalid'));
          $page->addPageError(
            pht(
              "URIs using the 'file://' protocol should have three slashes ".
              "(e.g., 'file:///absolute/path/to/file'). You only have two. ".
              "Add another one."));
        }
      }

      // Catch confusion between Git/SCP-style URIs and normal URIs. See T3619
      // for discussion. This is usually a user adding "ssh://" to an implicit
      // SSH Git URI.
      if ($proto == 'ssh') {
        if (preg_match('(^[^:@]+://[^/:]+:[^\d])', $v_remote)) {
          $c_remote->setError(pht('Invalid'));
          $page->addPageError(
            pht(
              "The Remote URI is not formatted correctly. Remote URIs ".
              "with an explicit protocol should be in the form ".
              "'proto://domain/path', not 'proto://domain:/path'. ".
              "The ':/path' syntax is only valid in SCP-style URIs."));
        }
      }

      switch ($proto) {
        case 'ssh':
        case 'http':
        case 'https':
        case 'file':
        case 'git':
        case 'svn':
        case 'svn+ssh':
          break;
        default:
          $c_remote->setError(pht('Invalid'));
          $page->addPageError(
            pht(
              "The URI protocol is unrecognized. It should begin ".
              "'ssh://', 'http://', 'https://', 'file://', 'git://', ".
              "'svn://', 'svn+ssh://', or be in the form ".
              "'git@domain.com:path'."));
          break;
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
        id(new AphrontFormTextControl())
          ->setName('ssh-login')
          ->setLabel('SSH User'))
      ->addControl(
        id(new AphrontFormTextAreaControl())
          ->setName('ssh-key')
          ->setLabel('SSH Private Key')
          ->setHeight(AphrontFormTextAreaControl::HEIGHT_SHORT)
          ->setCaption(
            hsprintf('Specify the entire private key, <em>or</em>...')))
      ->addControl(
        id(new AphrontFormTextControl())
          ->setName('ssh-keyfile')
          ->setLabel('SSH Private Key Path')
          ->setCaption(
            '...specify a path on disk where the daemon should '.
            'look for a private key.'))
      ->addControl(
        id(new AphrontFormTextControl())
          ->setName('http-login')
          ->setLabel('Username'))
      ->addControl(
        id(new AphrontFormPasswordControl())
          ->setName('http-pass')
          ->setLabel('Password'));
  }


  public function adjustAuthPage($page) {
    $form = $page->getForm();

    $remote_uri = $form->getPage('remote-uri')
      ->getControl('remoteURI')
      ->getValue();

    if ($this->getRepository()) {
      $vcs = $this->getRepository()->getVersionControlSystem();
    } else {
      $vcs = $form->getPage('vcs')->getControl('vcs')->getValue();
    }

    $proto = $this->getRemoteURIProtocol($remote_uri);
    $remote_user = $this->getRemoteURIUser($remote_uri);

    $page->getControl('ssh-login')->setHidden(true);
    $page->getControl('ssh-key')->setHidden(true);
    $page->getControl('ssh-keyfile')->setHidden(true);
    $page->getControl('http-login')->setHidden(true);
    $page->getControl('http-pass')->setHidden(true);

    if ($this->isSSHProtocol($proto)) {
      $page->getControl('ssh-login')->setHidden(false);
      $page->getControl('ssh-key')->setHidden(false);
      $page->getControl('ssh-keyfile')->setHidden(false);

      $c_login = $page->getControl('ssh-login');
      if (!strlen($c_login->getValue())) {
        $c_login->setValue($remote_user);
      }

      $page->addRemarkupInstructions(
        pht(
          'Enter the username and private key to use to connect to the '.
          'the repository hosted at:'.
          "\n\n".
          "  lang=text\n".
          "  %s".
          "\n\n".
          'You can either copy/paste the entire private key, or put it '.
          'somewhere on disk and provide the path to it.',
          $remote_uri),
        'ssh-login');

    } else if ($this->isUsernamePasswordProtocol($proto)) {
      $page->getControl('http-login')->setHidden(false);
      $page->getControl('http-pass')->setHidden(false);

      $page->addRemarkupInstructions(
        pht(
          'Enter the a username and pasword used to connect to the '.
          'repository hosted at:'.
          "\n\n".
          "  lang=text\n".
          "  %s".
          "\n\n".
          "If this repository does not require a username or password, ".
          "you can leave these fields blank.",
          $remote_uri),
        'http-login');
    } else if ($proto == 'file') {
      $page->addRemarkupInstructions(
        pht(
          'You do not need to configure any authentication options for '.
          'repositories accessed over the `file://` protocol. Continue '.
          'to the next step.'),
        'ssh-login');
    } else {
      throw new Exception("Unknown URI protocol!");
    }
  }

  public function validateAuthPage(PHUIFormPageView $page) {
    $form = $page->getForm();
    $remote_uri = $form->getPage('remote')->getControl('remoteURI')->getValue();
    $proto = $this->getRemoteURIProtocol($remote_uri);

    if ($this->isSSHProtocol($proto)) {
      $c_user = $page->getControl('ssh-login');
      $c_key = $page->getControl('ssh-key');
      $c_file = $page->getControl('ssh-keyfile');

      $v_user = $c_user->getValue();
      $v_key = $c_key->getValue();
      $v_file = $c_file->getValue();

      if (!strlen($v_user)) {
        $c_user->setError(pht('Required'));
        $page->addPageError(
          pht('You must provide an SSH login username to connect over SSH.'));
      }

      if (!strlen($v_key) && !strlen($v_file)) {
        $c_key->setError(pht('No Key'));
        $c_file->setError(pht('No Key'));
        $page->addPageError(
          pht(
            'You must provide either a private key or the path to a private '.
            'key to connect over SSH.'));
      } else if (strlen($v_key) && strlen($v_file)) {
        $c_key->setError(pht('Ambiguous'));
        $c_file->setError(pht('Ambiguous'));
        $page->addPageError(
          pht(
            'Provide either a private key or the path to a private key, not '.
            'both.'));
      } else if (!preg_match('/PRIVATE KEY/', $v_key)) {
        $c_key->setError(pht('Invalid'));
        $page->addPageError(
          pht(
            'The private key you provided is missing the PRIVATE KEY header. '.
            'You should include the header and footer. (Did you provide a '.
            'public key by mistake?)'));
      }

      return $c_user->isValid() &&
             $c_key->isValid() &&
             $c_file->isValid();
    } else if ($this->isUsernamePasswordProtocol($proto)) {
      return true;
    } else {
      return true;
    }
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


  private function getRemoteURIProtocol($raw_uri) {
    $uri = new PhutilURI($raw_uri);
    if ($uri->getProtocol()) {
      return strtolower($uri->getProtocol());
    }

    $git_uri = new PhutilGitURI($raw_uri);
    if (strlen($git_uri->getDomain()) && strlen($git_uri->getPath())) {
      return 'ssh';
    }

    return null;
  }

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
