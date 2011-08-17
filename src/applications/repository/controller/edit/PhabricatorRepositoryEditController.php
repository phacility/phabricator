<?php

/*
 * Copyright 2011 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

class PhabricatorRepositoryEditController
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

    $vcs = $repository->getVersionControlSystem();
    if ($vcs == DifferentialRevisionControlSystem::GIT) {
      if (!$repository->getDetail('github-token')) {
        $token = substr(base64_encode(Filesystem::readRandomBytes(8)), 0, 8);
        $repository->setDetail('github-token', $token);

        $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
        $repository->save();
        unset($unguarded);
      }

      $views['github'] = 'GitHub';
    }

    $this->repository = $repository;

    if (!isset($views[$this->view])) {
      reset($views);
      $this->view = key($views);
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

    $this->sideNav = $nav;

    switch ($this->view) {
      case 'basic':
        return $this->processBasicRequest();
      case 'tracking':
        return $this->processTrackingRequest();
      case 'github':
        return $this->processGithubRequest();
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

    switch ($repository->getVersionControlSystem()) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
        $is_git = true;
        break;
      case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
        $is_svn = true;
        break;
      default:
        throw new Exception("Unsupported VCS!");
    }

    if ($request->isFormPost()) {
      $tracking = ($request->getStr('tracking') == 'enabled' ? true : false);
      $repository->setDetail('tracking-enabled', $tracking);
      $repository->setDetail('remote-uri', $request->getStr('uri'));
      if ($is_git) {
        $repository->setDetail('local-path', $request->getStr('path'));
      }
      $repository->setDetail(
        'pull-frequency',
        max(1, $request->getInt('frequency')));

      if ($is_git) {
        $repository->setDetail(
          'default-branch',
          $request->getStr('default-branch'));
      }

      $repository->setDetail(
        'default-owners-path',
        $request->getStr(
          'default-owners-path',
          '/'));

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

        if ($is_git) {
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
      $error_view->appendChild(
        'Tracking changes were saved. You may need to restart the daemon '.
        'before changes will take effect.');
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
        '<strong>'.$user_guide_link.'</strong>.</p>')
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel('Repository')
          ->setValue($repository->getName()))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setName('tracking')
          ->setLabel('Tracking')
          ->setOptions(array(
            'disabled'  => 'Disabled',
            'enabled'   => 'Enabled',
          ))
          ->setValue(
            $repository->getDetail('tracking-enabled')
              ? 'enabled'
              : 'disabled'));

    $uri_label = 'Repository URI';
    if ($is_git) {
      $instructions =
        'NOTE: The user the tracking daemon runs as must have permission to '.
        '<tt>git clone</tt> from this URI.';
      $form->appendChild(
        '<p class="aphront-form-instructions">'.$instructions.'</p>');
    } else if ($is_svn) {
      $instructions =
        'Enter the <strong>Repository Root</strong> for this SVN repository. '.
        'You can figure this out by running <tt>svn info</tt> and looking at '.
        'the value in the <tt>Repository Root</tt> field. It should be a URI '.
        'and look like <tt>http://svn.example.org/svn/</tt> or '.
        '<tt>svn+ssh://svn.example.com/svnroot/</tt>.'.
        '<br /><br />'.
        'NOTE: The user the daemons run as must be able to execute '.
        '<tt>svn log</tt> against this URI.';
      $form->appendChild(
        '<p class="aphront-form-instructions">'.$instructions.'</p>');
      $uri_label = 'Repository Root';
    }

    $form
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setName('uri')
          ->setLabel($uri_label)
          ->setValue($repository->getDetail('remote-uri'))
          ->setError($e_uri));

    if ($is_git) {
      $form->appendChild(
        '<p class="aphront-form-instructions">Select a path on local disk '.
        'which the daemons should <tt>git clone</tt> the repository into. '.
        'This must be readable and writable by the daemons, and readable by '.
        'the webserver. The daemons will <tt>git fetch</tt> and keep this '.
        'repository up to date.</p>');
      $form->appendChild(
        id(new AphrontFormTextControl())
          ->setName('path')
          ->setLabel('Local Path')
          ->setValue($repository->getDetail('local-path'))
          ->setError($e_path));
    } else if ($is_svn) {
      $form->appendChild(
        '<p class="aphront-form-instructions">If you only want to parse one '.
        'subpath of the repository, specify it here, relative to the '.
        'repository root (e.g., <tt>trunk/</tt> or <tt>projects/wheel/</tt>). '.
        'If you want to parse multiple subdirectories, create a separate '.
        'Phabricator repository for each one.</p>');
      $form->appendChild(
        id(new AphrontFormTextControl())
          ->setName('svn-subpath')
          ->setLabel('Subpath')
          ->setValue($repository->getDetail('svn-subpath'))
          ->setError($e_path));
    }

    $form
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setName('frequency')
          ->setLabel('Pull Frequency')
          ->setValue($repository->getDetail('pull-frequency', 15))
          ->setCaption(
            'Number of seconds daemon should sleep between requests. Larger '.
            'numbers reduce load but also decrease responsiveness.'));

    if ($is_git) {
      $form
        ->appendChild(
          id(new AphrontFormTextControl())
            ->setName('default-branch')
            ->setLabel('Default Branch')
            ->setValue(
              $repository->getDetail(
                'default-branch',
                'origin/master'))
            ->setCaption(
              'Default <strong>remote</strong> branch to show in Diffusion.'));
    }

    $form
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setName('default-owners-path')
          ->setLabel('Default Owners Path')
          ->setValue(
            $repository->getDetail(
              'default-owners-path',
              '/'))
          ->setCaption('Default path in Owners tool.'));

    $form
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setName('herald-disabled')
          ->setLabel('Herald Enabled')
          ->setValue($repository->getDetail('herald-disabled', 0))
          ->setOptions(
            array(
              0 => 'Enabled - Send Email',
              1 => 'Disabled - Do Not Send Email',
            ))
          ->setCaption(
            'You can temporarily disable Herald notifications when reparsing '.
            'a repository or importing a new repository.'));

    $parsers = id(new PhutilSymbolLoader())
      ->setAncestorClass('PhabricatorRepositoryCommitMessageDetailParser')
      ->selectSymbolsWithoutLoading();
    $parsers = ipull($parsers, 'name', 'name');

    $form
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
      $form
        ->appendChild(
          id(new AphrontFormTextControl())
            ->setName('uuid')
            ->setLabel('UUID')
            ->setValue($repository->getUUID())
            ->setCaption('Repository UUID from <tt>svn info</tt>.'));
    }

    $form
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue('Save'));

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


  private function processGithubRequest() {
    $request = $this->getRequest();
    $repository = $this->repository;
    $repository_id = $repository->getID();

    $token = $repository->getDetail('github-token');
    $path = '/github-post-receive/'.$repository_id.'/'.$token.'/';
    $post_uri = PhabricatorEnv::getURI($path);

    $gitform = new AphrontFormLayoutView();
    $gitform
      ->setBackgroundShading(true)
      ->setPadded(true)
      ->appendChild(
        '<p class="aphront-form-instructions">You can configure GitHub to '.
        'notify Phabricator after changes are pushed. Log into GitHub, go '.
        'to "Admin" &rarr; "Service Hooks" &rarr; "Post-Receive URLs", and '.
        'add this URL to the list. Obviously, this will only work if your '.
        'Phabricator installation is accessible from the internet.</p>')
      ->appendChild(
        '<p class="aphront-form-instructions"> If things are working '.
        'properly, push notifications should appear below once you make some '.
        'commits.</p>')
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('URL')
          ->setCaption('Set this as a GitHub "Post-Receive URL".')
          ->setValue($post_uri))
      ->appendChild('<br /><br />')
      ->appendChild('<h1>Recent Commit Notifications</h1>');

    $notifications = id(new PhabricatorRepositoryGitHubNotification())
      ->loadAllWhere(
        'repositoryPHID = %s ORDER BY id DESC limit 10',
        $repository->getPHID());

    $rows = array();
    foreach ($notifications as $notification) {
      $rows[] = array(
        phutil_escape_html($notification->getRemoteAddress()),
        phabricator_format_timestamp($notification->getDateCreated()),
        $notification->getPayload()
          ? phutil_escape_html(substr($notification->getPayload(), 0, 32).'...')
          : 'Empty',
      );
    }

    $notification_table = new AphrontTableView($rows);
    $notification_table->setHeaders(
      array(
        'Remote Address',
        'Received',
        'Payload',
      ));
    $notification_table->setColumnClasses(
      array(
        null,
        null,
        'wide',
      ));
    $notification_table->setNoDataString(
      'Phabricator has not yet received any commit notifications for this '.
      'repository from GitHub.');

    $gitform->appendChild($notification_table);

    $github = new AphrontPanelView();
    $github->setHeader('GitHub Integration');
    $github->appendChild($gitform);
    $github->setWidth(AphrontPanelView::WIDTH_FORM);

    $nav = $this->sideNav;
    $nav->appendChild($github);

    return $this->buildStandardPageResponse(
      $nav,
      array(
        'title' => 'Repository Github Integration',
      ));
  }
}
