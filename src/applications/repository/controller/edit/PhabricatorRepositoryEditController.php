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

  public function willProcessRequest(array $data) {
    $this->id = idx($data, 'id');
  }

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    $repository = id(new PhabricatorRepository())->load($this->id);
    if (!$repository) {
      return new Aphront404Response();
    }

    $vcs = $repository->getVersionControlSystem();
    if ($vcs == DifferentialRevisionControlSystem::GIT) {
      if (!$repository->getDetail('github-token')) {
        $token = substr(base64_encode(Filesystem::readRandomBytes(8)), 0, 8);
        $repository->setDetail('github-token', $token);
        $repository->save();
      }
    }

    $e_name = true;

    $type_map = array(
      'svn' => 'Subversion',
      'git' => 'Git',
    );
    $errors = array();

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
          ->setURI('/repository/');
      }

    }

    $error_view = null;
    if ($errors) {
      $error_view = new AphrontErrorView();
      $error_view->setErrors($errors);
      $error_view->setTitle('Form Errors');
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
          ->setValue($repository->getVersionControlSystem()));


    $form
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue('Save')
          ->addCancelButton('/repository/'));

    $panel = new AphrontPanelView();
    $panel->setHeader('Edit Repository');
    $panel->appendChild($form);
    $panel->setWidth(AphrontPanelView::WIDTH_FORM);

    $phid = $repository->getID();
    $token = $repository->getDetail('github-token');
    $path = '/github-post-receive/'.$phid.'/'.$token.'/';
    $post_uri = PhabricatorEnv::getURI($path);

    $gitform = new AphrontFormView();
    $gitform
      ->setUser($user)
      ->setAction('/repository/edit/'.$repository->getID().'/')
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

    return $this->buildStandardPageResponse(
      array(
        $error_view,
        $panel,
        $github,
      ),
      array(
        'title' => 'Edit Repository',
      ));
  }

}
