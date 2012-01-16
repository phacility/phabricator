<?php

/*
 * Copyright 2012 Facebook, Inc.
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

class PhabricatorPeopleEditController extends PhabricatorPeopleController {

  public function shouldRequireAdmin() {
    return true;
  }

  private $id;
  private $view;

  public function willProcessRequest(array $data) {
    $this->id = idx($data, 'id');
    $this->view = idx($data, 'view');
  }

  public function processRequest() {

    $request = $this->getRequest();
    $admin = $request->getUser();

    if ($this->id) {
      $user = id(new PhabricatorUser())->load($this->id);
      if (!$user) {
        return new Aphront404Response();
      }
    } else {
      $user = new PhabricatorUser();
    }

    $views = array(
      'basic'     => 'Basic Information',
      'role'      => 'Edit Role',
      'cert'      => 'Conduit Certificate',
    );

    if (!$user->getID()) {
      $view = 'basic';
    } else if (isset($views[$this->view])) {
      $view = $this->view;
    } else {
      $view = 'basic';
    }

    $content = array();

    if ($request->getStr('saved')) {
      $notice = new AphrontErrorView();
      $notice->setSeverity(AphrontErrorView::SEVERITY_NOTICE);
      $notice->setTitle('Changes Saved');
      $notice->appendChild('<p>Your changes were saved.</p>');
      $content[] = $notice;
    }

    switch ($view) {
      case 'basic':
        $response = $this->processBasicRequest($user);
        break;
      case 'role':
        $response = $this->processRoleRequest($user);
        break;
      case 'cert':
        $response = $this->processCertificateRequest($user);
        break;
    }

    if ($response instanceof AphrontResponse) {
      return $response;
    }

    $content[] = $response;

    if ($user->getID()) {
      $side_nav = new AphrontSideNavView();
      $side_nav->appendChild($content);
      foreach ($views as $key => $name) {
        $side_nav->addNavItem(
          phutil_render_tag(
            'a',
            array(
              'href' => '/people/edit/'.$user->getID().'/'.$key.'/',
              'class' => ($key == $view)
                ? 'aphront-side-nav-selected'
                : null,
            ),
            phutil_escape_html($name)));
      }
      $content = $side_nav;
    }

    return $this->buildStandardPageResponse(
      $content,
      array(
        'title' => 'Edit User',
      ));
  }

  private function processBasicRequest(PhabricatorUser $user) {
    $request = $this->getRequest();
    $admin = $request->getUser();

    $e_username = true;
    $e_realname = true;
    $e_email    = true;
    $errors = array();

    $welcome_checked = true;

    $request = $this->getRequest();
    if ($request->isFormPost()) {
      $welcome_checked = $request->getInt('welcome');

      if (!$user->getID()) {
        $user->setUsername($request->getStr('username'));
        $user->setEmail($request->getStr('email'));

        if ($request->getStr('role') == 'agent') {
          $user->setIsSystemAgent(true);
        }
      }
      $user->setRealName($request->getStr('realname'));

      if (!strlen($user->getUsername())) {
        $errors[] = "Username is required.";
        $e_username = 'Required';
      } else if (!PhabricatorUser::validateUsername($user->getUsername())) {
        $errors[] = "Username must consist of only numbers and letters.";
        $e_username = 'Invalid';
      } else {
        $e_username = null;
      }

      if (!strlen($user->getRealName())) {
        $errors[] = 'Real name is required.';
        $e_realname = 'Required';
      } else {
        $e_realname = null;
      }

      if (!strlen($user->getEmail())) {
        $errors[] = 'Email is required.';
        $e_email = 'Required';
      } else {
        $e_email = null;
      }

      if (!$errors) {
        try {
          $is_new = !$user->getID();

          $user->save();

          if ($is_new) {
            $log = PhabricatorUserLog::newLog(
              $admin,
              $user,
              PhabricatorUserLog::ACTION_CREATE);
            $log->save();

            if ($welcome_checked) {
              $user->sendWelcomeEmail($admin);
            }
          }

          $response = id(new AphrontRedirectResponse())
            ->setURI('/people/edit/'.$user->getID().'/?saved=true');
          return $response;
        } catch (AphrontQueryDuplicateKeyException $ex) {
          $errors[] = 'Username and email must be unique.';

          $same_username = id(new PhabricatorUser())
            ->loadOneWhere('username = %s', $user->getUsername());
          $same_email = id(new PhabricatorUser())
            ->loadOneWhere('email = %s', $user->getEmail());

          if ($same_username) {
            $e_username = 'Duplicate';
          }

          if ($same_email) {
            $e_email = 'Duplicate';
          }
        }
      }
    }

    $error_view = null;
    if ($errors) {
      $error_view = id(new AphrontErrorView())
        ->setTitle('Form Errors')
        ->setErrors($errors);
    }

    $form = new AphrontFormView();
    $form->setUser($admin);
    if ($user->getID()) {
      $form->setAction('/people/edit/'.$user->getID().'/');
    } else {
      $form->setAction('/people/edit/');
    }

    if ($user->getID()) {
      $is_immutable = true;
    } else {
      $is_immutable = false;
    }

    $form
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Username')
          ->setName('username')
          ->setValue($user->getUsername())
          ->setError($e_username)
          ->setDisabled($is_immutable)
          ->setCaption('Usernames are permanent and can not be changed later!'))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Real Name')
          ->setName('realname')
          ->setValue($user->getRealName())
          ->setError($e_realname))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Email')
          ->setName('email')
          ->setDisabled($is_immutable)
          ->setValue($user->getEmail())
          ->setError($e_email));

    if (!$user->getID()) {
      $form
        ->appendChild(
          id(new AphrontFormSelectControl())
            ->setLabel('Role')
            ->setName('role')
            ->setValue('user')
            ->setOptions(
              array(
                'user'  => 'Normal User',
                'agent' => 'System Agent',
              ))
            ->setCaption(
              'You can create a "system agent" account for bots, scripts, '.
              'etc.'))
        ->appendChild(
          id(new AphrontFormCheckboxControl())
            ->addCheckbox(
              'welcome',
              1,
              'Send "Welcome to Phabricator" email.',
              $welcome_checked));
    } else {
      $form->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel('Role')
          ->setValue(
            $user->getIsSystemAgent()
              ? 'System Agent'
              : 'Normal User'));
    }

    $form
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue('Save'));

    $panel = new AphrontPanelView();
    if ($user->getID()) {
      $panel->setHeader('Edit User');
    } else {
      $panel->setHeader('Create New User');
    }

    $panel->appendChild($form);
    $panel->setWidth(AphrontPanelView::WIDTH_FORM);

    return array($error_view, $panel);
  }

  private function processRoleRequest(PhabricatorUser $user) {
    $request = $this->getRequest();
    $admin = $request->getUser();

    $is_self = ($user->getID() == $admin->getID());

    $errors = array();

    if ($request->isFormPost()) {

      $log_template = PhabricatorUserLog::newLog(
        $admin,
        $user,
        null);

      $logs = array();

      if ($is_self) {
        $errors[] = "You can not edit your own role.";
      } else {
        $new_admin = (bool)$request->getBool('is_admin');
        $old_admin = (bool)$user->getIsAdmin();
        if ($new_admin != $old_admin) {
          $log = clone $log_template;
          $log->setAction(PhabricatorUserLog::ACTION_ADMIN);
          $log->setOldValue($old_admin);
          $log->setNewValue($new_admin);
          $user->setIsAdmin($new_admin);
          $logs[] = $log;
        }

        $new_disabled = (bool)$request->getBool('is_disabled');
        $old_disabled = (bool)$user->getIsDisabled();
        if ($new_disabled != $old_disabled) {
          $log = clone $log_template;
          $log->setAction(PhabricatorUserLog::ACTION_DISABLE);
          $log->setOldValue($old_disabled);
          $log->setNewValue($new_disabled);
          $user->setIsDisabled($new_disabled);
          $logs[] = $log;
        }
      }

      if (!$errors) {
        $user->save();
        foreach ($logs as $log) {
          $log->save();
        }
        return id(new AphrontRedirectResponse())
          ->setURI($request->getRequestURI()->alter('saved', 'true'));
      }
    }

    $error_view = null;
    if ($errors) {
      $error_view = id(new AphrontErrorView())
        ->setTitle('Form Errors')
        ->setErrors($errors);
    }


    $form = id(new AphrontFormView())
      ->setUser($admin)
      ->setAction($request->getRequestURI()->alter('saved', null));

    if ($is_self) {
      $form->appendChild(
        '<p class="aphront-form-instructions">NOTE: You can not edit your own '.
        'role.</p>');
    }

    $form
      ->appendChild(
        id(new AphrontFormCheckboxControl())
          ->addCheckbox(
            'is_admin',
            1,
            'Admin: wields absolute power.',
            $user->getIsAdmin())
          ->setDisabled($is_self))
      ->appendChild(
        id(new AphrontFormCheckboxControl())
          ->addCheckbox(
            'is_disabled',
            1,
            'Disabled: can not login.',
            $user->getIsDisabled())
          ->setDisabled($is_self));

    if (!$is_self) {
      $form
        ->appendChild(
          id(new AphrontFormSubmitControl())
            ->setValue('Edit Role'));
    }

    $panel = new AphrontPanelView();
    $panel->setHeader('Edit Role');
    $panel->setWidth(AphrontPanelView::WIDTH_FORM);
    $panel->appendChild($form);

    return array($error_view, $panel);
  }

  private function processCertificateRequest($user) {
    $request = $this->getRequest();
    $admin = $request->getUser();


    $form = new AphrontFormView();
    $form
      ->setUser($admin)
      ->setAction($request->getRequestURI())
      ->appendChild(
        '<p class="aphront-form-instructions">You can use this certificate '.
        'to write scripts or bots which interface with Phabricator over '.
        'Conduit.</p>');

    if ($user->getIsSystemAgent()) {
      $form
        ->appendChild(
          id(new AphrontFormTextControl())
            ->setLabel('Username')
            ->setValue($user->getUsername()))
        ->appendChild(
          id(new AphrontFormTextAreaControl())
            ->setLabel('Certificate')
            ->setValue($user->getConduitCertificate()));
    } else {
      $form->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel('Certificate')
          ->setValue(
            'You may only view the certificates of System Agents.'));
    }

    $panel = new AphrontPanelView();
    $panel->setHeader('Conduit Certificate');
    $panel->setWidth(AphrontPanelView::WIDTH_FORM);

    $panel->appendChild($form);

    return array($panel);
  }

}
