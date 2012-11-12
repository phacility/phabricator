<?php

final class PhabricatorRepositoryEditController
  extends PhabricatorRepositoryController {

  private $id;
  private $view;
  private $repository;
  private $sideNav;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
    $this->view = idx($data, 'view');
  }

  public function processRequest() {

    $request = $this->getRequest();

    $repository = id(new PhabricatorRepository())->load($this->id);
    if (!$repository) {
      return new Aphront404Response();
    }

    $views = array(
      'basic'     => 'Basics',
      'tracking'  => 'Tracking',
    );

    $this->repository = $repository;

    if (!isset($views[$this->view])) {
      $this->view = head_key($views);
    }

    $nav = new AphrontSideNavView();
    foreach ($views as $view => $name) {
      $nav->addNavItem(
        phutil_render_tag(
          'a',
          array(
            'class' => ($view == $this->view
              ? 'aphront-side-nav-selected'
              : null),
            'href'  => '/repository/edit/'.$repository->getID().'/'.$view.'/',
          ),
          phutil_escape_html($name)));
    }

    $nav->appendChild($this->renderDaemonNotice());

    $this->sideNav = $nav;

    switch ($this->view) {
      case 'basic':
        return $this->processBasicRequest();
      case 'tracking':
        return $this->processTrackingRequest();
      default:
        throw new Exception("Unknown view.");
    }
  }

  protected function processBasicRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();
    $repository = $this->repository;
    $repository_id = $repository->getID();

    $errors = array();

    $e_name = true;

    if ($request->isFormPost()) {
      $repository->setName($request->getStr('name'));

      if (!strlen($repository->getName())) {
        $e_name = 'Required';
        $errors[] = 'Repository name is required.';
      } else {
        $e_name = null;
      }

      $repository->setDetail('description', $request->getStr('description'));
      $repository->setDetail('encoding', $request->getStr('encoding'));

      if (!$errors) {
        $repository->save();
        return id(new AphrontRedirectResponse())
          ->setURI('/repository/edit/'.$repository_id.'/basic/?saved=true');
      }
    }

    $error_view = null;
    if ($errors) {
      $error_view = new AphrontErrorView();
      $error_view->setErrors($errors);
      $error_view->setTitle('Form Errors');
    } else if ($request->getStr('saved')) {
      $error_view = new AphrontErrorView();
      $error_view->setSeverity(AphrontErrorView::SEVERITY_NOTICE);
      $error_view->setTitle('Changes Saved');
      $error_view->appendChild(
        'Repository changes were saved.');
    }

    $encoding_doc_link = PhabricatorEnv::getDoclink(
        'article/User_Guide_UTF-8_and_Character_Encoding.html');

    $form = new AphrontFormView();
    $form
      ->setUser($user)
      ->setAction('/repository/edit/'.$repository->getID().'/')
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Name')
          ->setName('name')
          ->setValue($repository->getName())
          ->setError($e_name)
          ->setCaption('Human-readable repository name.'))
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setLabel('Description')
          ->setName('description')
          ->setHeight(AphrontFormTextAreaControl::HEIGHT_VERY_SHORT)
          ->setValue($repository->getDetail('description')))
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel('Callsign')
          ->setName('callsign')
          ->setValue($repository->getCallsign()))
      ->appendChild('
        <p class="aphront-form-instructions">'.
          'If source code in this repository uses a character '.
          'encoding other than UTF-8 (for example, ISO-8859-1), '.
          'specify it here. You can usually leave this field blank. '.
          'See User Guide: '.
          '<a href="'.$encoding_doc_link.'">'.
            'UTF-8 and Character Encoding'.
          '</a> for more information.'.
        '</p>')
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Encoding')
          ->setName('encoding')
          ->setValue($repository->getDetail('encoding')))
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel('Type')
          ->setName('type')
          ->setValue($repository->getVersionControlSystem()))
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel('ID')
          ->setValue($repository->getID()))
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel('PHID')
          ->setValue($repository->getPHID()))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue('Save'));

    $panel = new AphrontPanelView();
    $panel->setHeader('Edit Repository');
    $panel->appendChild($form);
    $panel->setWidth(AphrontPanelView::WIDTH_FORM);


    $nav = $this->sideNav;

    $nav->appendChild($error_view);
    $nav->appendChild($panel);

    return $this->buildStandardPageResponse(
      $nav,
      array(
        'title' => 'Edit Repository',
      ));
  }

  private function processTrackingRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();
    $repository = $this->repository;
    $repository_id = $repository->getID();

    $errors = array();

    $e_uri = null;
    $e_path = null;

    $is_git = false;
    $is_svn = false;
    $is_mercurial = false;

    $e_ssh_key = null;
    $e_ssh_keyfile = null;
    $e_branch = null;

    switch ($repository->getVersionControlSystem()) {
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

    $has_branches       = ($is_git || $is_mercurial);
    $has_local          = ($is_git || $is_mercurial);
    $has_branch_filter  = ($is_git);
    $has_auth_support   = $is_svn;

    if ($request->isFormPost()) {
      $tracking = ($request->getStr('tracking') == 'enabled' ? true : false);
      $repository->setDetail('tracking-enabled', $tracking);
      $repository->setDetail('remote-uri', $request->getStr('uri'));
      if ($has_local) {
        $repository->setDetail('local-path', $request->getStr('path'));
      }

      if ($has_branch_filter) {
        $branch_filter = $request->getStrList('branch-filter');
        $branch_filter = array_fill_keys($branch_filter, true);

        $repository->setDetail('branch-filter', $branch_filter);

        $close_commits_filter = $request->getStrList('close-commits-filter');
        $close_commits_filter = array_fill_keys($close_commits_filter, true);

        $repository->setDetail('close-commits-filter', $close_commits_filter);
      }

      $repository->setDetail(
        'disable-autoclose',
        $request->getStr('autoclose') == 'disabled' ? true : false);

      $repository->setDetail(
        'pull-frequency',
        max(1, $request->getInt('frequency')));

      if ($has_branches) {
        $repository->setDetail(
          'default-branch',
          $request->getStr('default-branch'));
        if ($is_git) {
          $branch_name = $repository->getDetail('default-branch');
          if (strpos($branch_name, '/') !== false) {
            $e_branch = 'Invalid';
            $errors[] = "Your branch name should not specify an explicit ".
                        "remote. For instance, use 'master', not ".
                        "'origin/master'.";
          }
        }
      }

      $repository->setDetail(
        'default-owners-path',
        $request->getStr(
          'default-owners-path',
          '/'));

      $repository->setDetail('ssh-login', $request->getStr('ssh-login'));
      $repository->setDetail('ssh-key', $request->getStr('ssh-key'));
      $repository->setDetail('ssh-keyfile', $request->getStr('ssh-keyfile'));

      $repository->setDetail('http-login', $request->getStr('http-login'));
      $repository->setDetail('http-pass',  $request->getStr('http-pass'));

      if ($repository->getDetail('ssh-key') &&
          $repository->getDetail('ssh-keyfile')) {
        $errors[] =
          "Specify only one of 'SSH Private Key' and 'SSH Private Key File', ".
          "not both.";
        $e_ssh_key = 'Choose Only One';
        $e_ssh_keyfile = 'Choose Only One';
      }

      $repository->setDetail(
        'herald-disabled',
        $request->getInt('herald-disabled', 0));

      if ($is_svn) {
        $repository->setUUID($request->getStr('uuid'));
        $subpath = ltrim($request->getStr('svn-subpath'), '/');
        if ($subpath) {
          $subpath = rtrim($subpath, '/').'/';
        }
        $repository->setDetail('svn-subpath', $subpath);
      }

      $repository->setDetail(
        'detail-parser',
        $request->getStr(
          'detail-parser',
          'PhabricatorRepositoryDefaultCommitMessageDetailParser'));

      if ($tracking) {
        if (!$repository->getDetail('remote-uri')) {
          $e_uri = 'Required';
          $errors[] = "Repository URI is required.";
        } else if ($is_svn &&
          !preg_match('@/$@', $repository->getDetail('remote-uri'))) {

          $e_uri = 'Invalid';
          $errors[] = 'Subversion Repository Root must end in a slash ("/").';
        } else {
          $e_uri = null;
        }

        if ($has_local) {
          if (!$repository->getDetail('local-path')) {
            $e_path = 'Required';
            $errors[] = "Local path is required.";
          } else {
            $e_path = null;
          }
        }
      }

      if (!$errors) {
        $repository->save();
        return id(new AphrontRedirectResponse())
          ->setURI('/repository/edit/'.$repository_id.'/tracking/?saved=true');
      }
    }

    $error_view = null;
    if ($errors) {
      $error_view = new AphrontErrorView();
      $error_view->setErrors($errors);
      $error_view->setTitle('Form Errors');
    } else if ($request->getStr('saved')) {
      $error_view = new AphrontErrorView();
      $error_view->setSeverity(AphrontErrorView::SEVERITY_NOTICE);
      $error_view->setTitle('Changes Saved');
      $error_view->appendChild('Tracking changes were saved.');
    } else if (!$repository->isTracked()) {
      $error_view = new AphrontErrorView();
      $error_view->setSeverity(AphrontErrorView::SEVERITY_WARNING);
      $error_view->setTitle('Repository Not Tracked');
      $error_view->appendChild(
        'Tracking is currently "Disabled" for this repository, so it will '.
        'not be imported into Phabricator. You can enable it below.');
    }

    switch ($repository->getVersionControlSystem()) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
        $is_git = true;
        break;
      case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
        $is_svn = true;
        break;
    }

    $doc_href = PhabricatorEnv::getDoclink('article/Diffusion_User_Guide.html');
    $user_guide_link = phutil_render_tag(
      'a',
      array(
        'href' => $doc_href,
      ),
      'Diffusion User Guide');

    $form = new AphrontFormView();
    $form
      ->setUser($user)
      ->setAction('/repository/edit/'.$repository->getID().'/tracking/')
      ->appendChild(
        '<p class="aphront-form-instructions">Phabricator can track '.
        'repositories, importing commits as they happen and notifying '.
        'Differential, Diffusion, Herald, and other services. To enable '.
        'tracking for a repository, configure it here and then start (or '.
        'restart) the daemons. More information is available in the '.
        '<strong>'.$user_guide_link.'</strong>.</p>');

    $form
      ->appendChild(
        id(new AphrontFormInsetView())
          ->setTitle('Basics')
          ->appendChild(id(new AphrontFormStaticControl())
            ->setLabel('Repository Name')
            ->setValue($repository->getName()))
          ->appendChild(id(new AphrontFormSelectControl())
            ->setName('tracking')
            ->setLabel('Tracking')
            ->setOptions(array(
                'disabled'  => 'Disabled',
                'enabled'   => 'Enabled',
                ))
            ->setValue(
              $repository->isTracked()
              ? 'enabled'
              : 'disabled')));

    $inset = new AphrontFormInsetView();
    $inset->setTitle('Remote URI');

    $clone_command = null;
    $fetch_command = null;
    if ($is_git) {
      $clone_command = 'git clone';
      $fetch_command = 'git fetch';
    } else if ($is_mercurial) {
      $clone_command = 'hg clone';
      $fetch_command = 'hg pull';
    }

    $uri_label = 'Repository URI';
    if ($has_local) {
      if ($is_git) {
        $instructions =
          'Enter the URI to clone this repository from. It should look like '.
          '<tt>git@github.com:example/example.git</tt>, '.
          '<tt>ssh://user@host.com/git/example.git</tt>, or '.
          '<tt>file:///local/path/to/repo</tt>';
      } else if ($is_mercurial) {
        $instructions =
          'Enter the URI to clone this repository from. It should look '.
          'something like <tt>ssh://user@host.com/hg/example</tt>';
      }
      $inset->appendChild(
        '<p class="aphront-form-instructions">'.$instructions.'</p>');
    } else if ($is_svn) {
      $instructions =
        'Enter the <strong>Repository Root</strong> for this SVN repository. '.
        'You can figure this out by running <tt>svn info</tt> and looking at '.
        'the value in the <tt>Repository Root</tt> field. It should be a URI '.
        'and look like <tt>http://svn.example.org/svn/</tt>, '.
        '<tt>svn+ssh://svn.example.com/svnroot/</tt>, or '.
        '<tt>svn://svn.example.net/svn/</tt>';
      $inset->appendChild(
        '<p class="aphront-form-instructions">'.$instructions.'</p>');
      $uri_label = 'Repository Root';
    }

    $inset
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setName('uri')
          ->setLabel($uri_label)
          ->setID('remote-uri')
          ->setValue($repository->getDetail('remote-uri'))
          ->setError($e_uri));

    $inset->appendChild(
      '<div class="aphront-form-instructions">'.
        'If you want to connect to this repository over SSH, enter the '.
        'username and private key to use. You can leave these fields blank if '.
        'the repository does not use SSH.'.
      '</div>');

    $inset
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setName('ssh-login')
          ->setLabel('SSH User')
          ->setValue($repository->getDetail('ssh-login')))
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setName('ssh-key')
          ->setLabel('SSH Private Key')
          ->setHeight(AphrontFormTextAreaControl::HEIGHT_VERY_SHORT)
          ->setValue($repository->getDetail('ssh-key'))
          ->setError($e_ssh_key)
          ->setCaption('Specify the entire private key, <em>or</em>...'))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setName('ssh-keyfile')
          ->setLabel('SSH Private Key File')
          ->setValue($repository->getDetail('ssh-keyfile'))
          ->setError($e_ssh_keyfile)
          ->setCaption(
            '...specify a path on disk where the daemon should '.
            'look for a private key.'));

    if ($has_auth_support) {
      $inset
        ->appendChild(
          '<div class="aphront-form-instructions">'.
            'If you want to connect to this repository with a username and '.
            'password, such as over HTTP Basic Auth or SVN with SASL, '.
            'enter the username and password to use. You can leave these '.
            'fields blank if the repository does not use a username and '.
            'password for authentication.'.
          '</div>')
        ->appendChild(
          id(new AphrontFormTextControl())
            ->setName('http-login')
            ->setLabel('Username')
            ->setValue($repository->getDetail('http-login')))
        ->appendChild(
          id(new AphrontFormPasswordControl())
            ->setName('http-pass')
            ->setLabel('Password')
            ->setValue($repository->getDetail('http-pass')));
    }

    $inset
      ->appendChild(
        '<div class="aphront-form-important">'.
          'To test your authentication configuration, <strong>save this '.
          'form</strong> and then run this script:'.
          '<code>'.
            'phabricator/ $ ./scripts/repository/test_connection.php '.
            phutil_escape_html($repository->getCallsign()).
          '</code>'.
          'This will verify that your configuration is correct and the '.
          'daemons can connect to the remote repository and pull changes '.
          'from it.'.
        '</div>');

    $form->appendChild($inset);

    $inset = new AphrontFormInsetView();
    $inset->setTitle('Repository Information');

    if ($has_local) {
      $inset->appendChild(
        '<p class="aphront-form-instructions">Select a path on local disk '.
        'which the daemons should <tt>'.$clone_command.'</tt> the repository '.
        'into. This must be readable and writable by the daemons, and '.
        'readable by the webserver. The daemons will <tt>'.$fetch_command.
        '</tt> and keep this repository up to date.</p>');
      $inset->appendChild(
        id(new AphrontFormTextControl())
          ->setName('path')
          ->setLabel('Local Path')
          ->setValue($repository->getDetail('local-path'))
          ->setError($e_path));
    } else if ($is_svn) {
      $inset->appendChild(
        '<p class="aphront-form-instructions">If you only want to parse one '.
        'subpath of the repository, specify it here, relative to the '.
        'repository root (e.g., <tt>trunk/</tt> or <tt>projects/wheel/</tt>). '.
        'If you want to parse multiple subdirectories, create a separate '.
        'Phabricator repository for each one.</p>');
      $inset->appendChild(
        id(new AphrontFormTextControl())
          ->setName('svn-subpath')
          ->setLabel('Subpath')
          ->setValue($repository->getDetail('svn-subpath'))
          ->setError($e_path));
    }

    if ($has_branch_filter) {
      $branch_filter_str = implode(
        ', ',
        array_keys($repository->getDetail('branch-filter', array())));
      $inset
        ->appendChild(
          id(new AphrontFormTextControl())
            ->setName('branch-filter')
            ->setLabel('Track Only')
            ->setValue($branch_filter_str)
            ->setCaption(
              'Optional list of branches to track. Other branches will be '.
              'completely ignored. If left empty, all branches are tracked. '.
              'Example: <tt>master, release</tt>'));
    }

    $inset
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setName('frequency')
          ->setLabel('Pull Frequency')
          ->setValue($repository->getDetail('pull-frequency', 15))
          ->setCaption(
            'Number of seconds daemon should sleep between requests. Larger '.
            'numbers reduce load but also decrease responsiveness.'));

    $form->appendChild($inset);

    $inset = new AphrontFormInsetView();
    $inset->setTitle('Application Configuration');

    if ($has_branches) {
      $inset
        ->appendChild(
          id(new AphrontFormTextControl())
            ->setName('default-branch')
            ->setLabel('Default Branch')
            ->setValue($repository->getDefaultBranch())
            ->setError($e_branch)
            ->setCaption(
              'Default branch to show in Diffusion.'));
    }

    $inset
      ->appendChild(id(new AphrontFormSelectControl())
        ->setName('autoclose')
        ->setLabel('Autoclose')
        ->setOptions(array(
            'enabled'   => 'Enabled: Automatically Close Pushed Revisions',
            'disabled'  => 'Disabled: Ignore Pushed Revisions',
            ))
        ->setCaption(
          "Automatically close Differential revisions when associated commits ".
          "are pushed to this repository.")
        ->setValue(
          $repository->getDetail('disable-autoclose', false)
          ? 'disabled'
          : 'enabled'));

    if ($has_branch_filter) {
      $close_commits_filter_str = implode(
          ', ',
          array_keys($repository->getDetail('close-commits-filter', array())));
      $inset
        ->appendChild(
          id(new AphrontFormTextControl())
            ->setName('close-commits-filter')
            ->setLabel('Autoclose Branches')
            ->setValue($close_commits_filter_str)
            ->setCaption(
              'Optional list of branches which can trigger autoclose. '.
              'If left empty, all branches trigger autoclose.'));
    }

    $inset
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setName('default-owners-path')
          ->setLabel('Default Owners Path')
          ->setValue(
            $repository->getDetail(
              'default-owners-path',
              '/'))
          ->setCaption('Default path in Owners tool.'));

    $inset
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setName('herald-disabled')
          ->setLabel('Herald/Feed Enabled')
          ->setValue($repository->getDetail('herald-disabled', 0))
          ->setOptions(
            array(
              0 => 'Enabled - Send Email and Publish Stories',
              1 => 'Disabled - Do Not Send Email or Publish Stories',
            ))
          ->setCaption(
            'You can disable Herald commit notifications and feed stories '.
            'for this repository. This can be useful when initially importing '.
            'a repository. Feed stories are never published about commits '.
            'that are more than 24 hours old.'));

    $parsers = id(new PhutilSymbolLoader())
      ->setAncestorClass('PhabricatorRepositoryCommitMessageDetailParser')
      ->selectSymbolsWithoutLoading();
    $parsers = ipull($parsers, 'name', 'name');

    $inset
      ->appendChild(
        '<p class="aphront-form-instructions">If you extend the commit '.
        'message format, you can provide a new parser which will extract '.
        'extra information from it when commits are imported. This is an '.
        'advanced feature, and using the default parser will be suitable '.
        'in most cases.</p>')
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setName('detail-parser')
          ->setLabel('Detail Parser')
          ->setOptions($parsers)
          ->setValue(
            $repository->getDetail(
              'detail-parser',
              'PhabricatorRepositoryDefaultCommitMessageDetailParser')));

    if ($is_svn) {
      $inset
        ->appendChild(
          id(new AphrontFormTextControl())
            ->setName('uuid')
            ->setLabel('UUID')
            ->setValue($repository->getUUID())
            ->setCaption('Repository UUID from <tt>svn info</tt>.'));
    }

    $form->appendChild($inset);

    $form
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue('Save Configuration'));

    $panel = new AphrontPanelView();
    $panel->setHeader('Repository Tracking');
    $panel->appendChild($form);
    $panel->setWidth(AphrontPanelView::WIDTH_WIDE);

    $nav = $this->sideNav;
    $nav->appendChild($error_view);
    $nav->appendChild($panel);

    return $this->buildStandardPageResponse(
      $nav,
      array(
        'title' => 'Edit Repository Tracking',
      ));
  }

}
