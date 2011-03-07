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

class PhabricatorRepositoryEditController extends PhabricatorController {

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
    $user = $request->getUser();

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
        $repository->save();
      }

      $views['github'] = 'Github';
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

    $type_map = array(
      'svn' => 'Subversion',
      'git' => 'Git',
    );

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
          ->setLabel('PHID')
          ->setName('phid')
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

    if ($request->isFormPost()) {
      $tracking = ($request->getStr('tracking') == 'enabled' ? true : false);
      $repository->setDetail('tracking-enabled', $tracking);
      $repository->setDetail('remote-uri', $request->getStr('uri'));
      $repository->setDetail('local-path', $request->getStr('path'));
      $repository->setDetail(
        'pull-frequency',
        max(1, $request->getInt('frequency')));

      if ($tracking) {
        if (!$repository->getDetail('remote-uri')) {
          $e_uri = 'Required';
          $errors[] = "Repository URI is required.";
        }
        if (!$repository->getDetail('local-path')) {
          $e_path = 'Required';
          $errors[] = "Local path is required.";
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

    $uri_caption = null;
    $path_caption = null;
    switch ($repository->getVersionControlSystem()) {
      case 'git':
        $uri_caption =
          'The user the tracking daemon runs as must have permission to '.
          '<tt>git clone</tt> from this URI.';
        $path_caption =
          'Directory where the daemon should look to find a copy of the '.
          'repository (or create one if it does not yet exist). The daemon '.
          'will regularly pull remote changes into this working copy.';
        break;
      case 'svn':
        $uri_caption =
          'The user the tracking daemon runs as must have permission to '.
          '<tt>svn log</tt> from this URI.';
        break;
    }

    $form = new AphrontFormView();
    $form
      ->setUser($user)
      ->setAction('/repository/edit/'.$repository->getID().'/tracking/')
      ->appendChild(
        '<p class="aphront-form-instructions">Phabricator can track '.
        'repositories, importing commits as they happen and notifying '.
        'Differential, Diffusion, Herald, and other services. To enable '.
        'tracking for a repository, configure it here and then start (or '.
        'restart) the PhabricatorRepositoryTrackingDaemon.</p>')
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
              : 'disabled'))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setName('uri')
          ->setLabel('URI')
          ->setValue($repository->getDetail('remote-uri'))
          ->setError($e_uri)
          ->setCaption($uri_caption))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setName('path')
          ->setLabel('Local Path')
          ->setValue($repository->getDetail('local-path'))
          ->setError($e_path)
          ->setCaption($path_caption))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setName('frequency')
          ->setLabel('Pull Frequency')
          ->setValue($repository->getDetail('pull-frequency', 15))
          ->setCaption(
            'Number of seconds daemon should sleep between requests. Larger '.
            'numbers reduce load but also decrease responsiveness.'))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue('Save'));

    $panel = new AphrontPanelView();
    $panel->setHeader('Repository Tracking');
    $panel->appendChild($form);
    $panel->setWidth(AphrontPanelView::WIDTH_FORM);

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
    $user = $request->getUser();
    $repository = $this->repository;
    $repository_id = $repository->getID();

    $token = $repository->getDetail('github-token');
    $path = '/github-post-receive/'.$repository_id.'/'.$token.'/';
    $post_uri = PhabricatorEnv::getURI($path);

    $gitform = new AphrontFormView();
    $gitform
      ->setUser($user)
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
