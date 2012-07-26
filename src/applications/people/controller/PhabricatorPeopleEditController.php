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

final class PhabricatorPeopleEditController
  extends PhabricatorPeopleController {

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
      $base_uri = '/people/edit/'.$user->getID().'/';
    } else {
      $user = new PhabricatorUser();
      $base_uri = '/people/edit/';
    }

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($base_uri));
    $nav->addFilter('basic', 'Basic Information');
    $nav->addFilter('role',  'Edit Roles');
    $nav->addFilter('cert',  'Conduit Certificate');
    $nav->addSpacer();
    $nav->addFilter('rename', 'Change Username');
    $nav->addFilter('delete', 'Delete User');

    if (!$user->getID()) {
      $this->view = 'basic';
    }
    $view = $nav->selectFilter($this->view, 'basic');

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
      case 'rename':
        $response = $this->processRenameRequest($user);
        break;
      case 'delete':
        $response = $this->processDeleteRequest($user);
        break;
      default:
        return new Aphront404Response();
    }

    if ($response instanceof AphrontResponse) {
      return $response;
    }

    $content[] = $response;

    if ($user->getID()) {
      $nav->appendChild($content);
      $content = $nav;
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

    $new_email = null;

    $request = $this->getRequest();
    if ($request->isFormPost()) {
      $welcome_checked = $request->getInt('welcome');

      if (!$user->getID()) {
        $user->setUsername($request->getStr('username'));

        $new_email = $request->getStr('email');
        if (!strlen($new_email)) {
          $errors[] = 'Email is required.';
          $e_email = 'Required';
        } else if (!PhabricatorUserEmail::isAllowedAddress($new_email)) {
          $e_email = 'Invalid';
          $errors[] = PhabricatorUserEmail::describeAllowedAddresses();
        } else {
          $e_email = null;
        }

        if ($request->getStr('role') == 'agent') {
          $user->setIsSystemAgent(true);
        }
      }
      $user->setRealName($request->getStr('realname'));

      if (!strlen($user->getUsername())) {
        $errors[] = "Username is required.";
        $e_username = 'Required';
      } else if (!PhabricatorUser::validateUsername($user->getUsername())) {
        $errors[] = PhabricatorUser::describeValidUsername();
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

      if (!$errors) {
        try {
          $is_new = !$user->getID();

          if (!$is_new) {
            id(new PhabricatorUserEditor())
              ->setActor($admin)
              ->updateUser($user);
          } else {
            $email = id(new PhabricatorUserEmail())
              ->setAddress($new_email)
              ->setIsVerified(0);

            id(new PhabricatorUserEditor())
              ->setActor($admin)
              ->createNewUser($user, $email);
          }

          if ($welcome_checked) {
            $user->sendWelcomeEmail($admin);
          }

          $response = id(new AphrontRedirectResponse())
            ->setURI('/people/edit/'.$user->getID().'/?saved=true');
          return $response;
        } catch (AphrontQueryDuplicateKeyException $ex) {
          $errors[] = 'Username and email must be unique.';

          $same_username = id(new PhabricatorUser())
            ->loadOneWhere('username = %s', $user->getUsername());
          $same_email = id(new PhabricatorUserEmail())
            ->loadOneWhere('address = %s', $new_email);

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
          ->setDisabled($is_immutable))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Real Name')
          ->setName('realname')
          ->setValue($user->getRealName())
          ->setError($e_realname));

    if (!$user->getID()) {
      $form->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Email')
          ->setName('email')
          ->setDisabled($is_immutable)
          ->setValue($new_email)
          ->setCaption(PhabricatorUserEmail::describeAllowedAddresses())
          ->setError($e_email));
    } else {
      $email = $user->loadPrimaryEmail();
      if ($email) {
        $status = $email->getIsVerified() ? 'Verified' : 'Unverified';
      } else {
        $status = 'No Email Address';
      }

      $form->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel('Email')
          ->setValue($status));

      $form->appendChild(
        id(new AphrontFormCheckboxControl())
        ->addCheckbox(
          'welcome',
          1,
          'Re-send "Welcome to Phabricator" email.',
          false));

    }

    $form->appendChild($this->getRoleInstructions());

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
      $roles = array();

      if ($user->getIsSystemAgent()) {
        $roles[] = 'System Agent';
      }
      if ($user->getIsAdmin()) {
        $roles[] = 'Admin';
      }
      if ($user->getIsDisabled()) {
        $roles[] = 'Disabled';
      }

      if (!$roles) {
        $roles[] = 'Normal User';
      }

      $roles = implode(', ', $roles);

      $form->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel('Roles')
          ->setValue($roles));
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
          id(new PhabricatorUserEditor())
            ->setActor($admin)
            ->makeAdminUser($user, $new_admin);
        }

        $new_disabled = (bool)$request->getBool('is_disabled');
        $old_disabled = (bool)$user->getIsDisabled();
        if ($new_disabled != $old_disabled) {
          id(new PhabricatorUserEditor())
            ->setActor($admin)
            ->disableUser($user, $new_disabled);
        }
      }

      if (!$errors) {
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
      ->appendChild($this->getRoleInstructions())
      ->appendChild(
        id(new AphrontFormCheckboxControl())
          ->addCheckbox(
            'is_admin',
            1,
            'Administrator',
            $user->getIsAdmin())
          ->setDisabled($is_self))
      ->appendChild(
        id(new AphrontFormCheckboxControl())
          ->addCheckbox(
            'is_disabled',
            1,
            'Disabled',
            $user->getIsDisabled())
          ->setDisabled($is_self))
      ->appendChild(
        id(new AphrontFormCheckboxControl())
          ->addCheckbox(
            'is_agent',
            1,
            'System Agent (Bot/Script User)',
            $user->getIsSystemAgent())
          ->setDisabled(true));

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

  private function processRenameRequest(PhabricatorUser $user) {
    $request = $this->getRequest();
    $admin = $request->getUser();

    $e_username = true;
    $username = $user->getUsername();

    $errors = array();
    if ($request->isFormPost()) {

      $username = $request->getStr('username');
      if (!strlen($username)) {
        $e_username = 'Required';
        $errors[] = 'New username is required.';
      } else if ($username == $user->getUsername()) {
        $e_username = 'Invalid';
        $errors[] = 'New username must be different from old username.';
      } else if (!PhabricatorUser::validateUsername($username)) {
        $e_username = 'Invalid';
        $errors[] = PhabricatorUser::describeValidUsername();
      }

      if (!$errors) {
        try {

          id(new PhabricatorUserEditor())
            ->setActor($admin)
            ->changeUsername($user, $username);

          return id(new AphrontRedirectResponse())
            ->setURI($request->getRequestURI()->alter('saved', true));
        } catch (AphrontQueryDuplicateKeyException $ex) {
          $e_username = 'Not Unique';
          $errors[] = 'Another user already has that username.';
        }
      }
    }

    if ($errors) {
      $errors = id(new AphrontErrorView())
        ->setTitle('Form Errors')
        ->setErrors($errors);
    } else {
      $errors = null;
    }

    $form = new AphrontFormView();
    $form
      ->setUser($admin)
      ->setAction($request->getRequestURI())
      ->appendChild(
        '<p class="aphront-form-instructions">'.
          '<strong>Be careful when renaming users!</strong> '.
          'The old username will no longer be tied to the user, so anything '.
          'which uses it (like old commit messages) will no longer associate '.
          'correctly. And if you give a user a username which some other user '.
          'used to have, username lookups will begin returning the wrong '.
          'user.'.
        '</p>'.
        '<p class="aphront-form-instructions">'.
          'It is generally safe to rename newly created users (and test users '.
          'and so on), but less safe to rename established users and unsafe '.
          'to reissue a username.'.
        '</p>'.
        '<p class="aphront-form-instructions">'.
          'Users who rely on password auth will need to reset their password '.
          'after their username is changed (their username is part of the '.
          'salt in the password hash). They will receive an email with '.
          'instructions on how to do this.'.
        '</p>')
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel('Old Username')
          ->setValue($user->getUsername()))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('New Username')
          ->setValue($username)
          ->setName('username')
          ->setError($e_username))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue('Change Username'));

    $panel = new AphrontPanelView();
    $panel->setHeader('Change Username');
    $panel->setWidth(AphrontPanelView::WIDTH_FORM);
    $panel->appendChild($form);

    return array($errors, $panel);
  }

  private function processDeleteRequest(PhabricatorUser $user) {
    $request = $this->getRequest();
    $admin = $request->getUser();

    if ($user->getPHID() == $admin->getPHID()) {
      $error = new AphrontErrorView();
      $error->setTitle('You Shall Journey No Farther');
      $error->appendChild(
        '<p>As you stare into the gaping maw of the abyss, something holds '.
        'you back.</p>'.
        '<p>You can not delete your own account.</p>');
      return $error;
    }

    $e_username = true;
    $username = null;

    $errors = array();
    if ($request->isFormPost()) {

      $username = $request->getStr('username');
      if (!strlen($username)) {
        $e_username = 'Required';
        $errors[] = 'You must type the username to confirm deletion.';
      } else if ($username != $user->getUsername()) {
        $e_username = 'Invalid';
        $errors[] = 'You must type the username correctly.';
      }

      if (!$errors) {
        id(new PhabricatorUserEditor())
          ->setActor($admin)
          ->deleteUser($user);

        return id(new AphrontRedirectResponse())->setURI('/people/');
      }
    }

    if ($errors) {
      $errors = id(new AphrontErrorView())
        ->setTitle('Form Errors')
        ->setErrors($errors);
    } else {
      $errors = null;
    }

    $form = new AphrontFormView();
    $form
      ->setUser($admin)
      ->setAction($request->getRequestURI())
      ->appendChild(
        '<p class="aphront-form-instructions">'.
          '<strong>Be careful when deleting users!</strong> '.
          'If this user interacted with anything, it is generally better '.
          'to disable them, not delete them. If you delete them, it will '.
          'no longer be possible to search for their objects, for example, '.
          'and you will lose other information about their history. Disabling '.
          'them instead will prevent them from logging in but not destroy '.
          'any of their data.'.
        '</p>'.
        '<p class="aphront-form-instructions">'.
          'It is generally safe to delete newly created users (and test users '.
          'and so on), but less safe to delete established users. If '.
          'possible, disable them instead.'.
        '</p>')
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel('Username')
          ->setValue($user->getUsername()))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Confirm')
          ->setValue($username)
          ->setName('username')
          ->setCaption("Type the username again to confirm deletion.")
          ->setError($e_username))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue('Delete User'));

    $panel = new AphrontPanelView();
    $panel->setHeader('Delete User');
    $panel->setWidth(AphrontPanelView::WIDTH_FORM);
    $panel->appendChild($form);

    return array($errors, $panel);
  }

  private function getRoleInstructions() {
    $roles_link = phutil_render_tag(
      'a',
      array(
        'href'   => PhabricatorEnv::getDoclink(
          'article/User_Guide_Account_Roles.html'),
        'target' => '_blank',
      ),
      'User Guide: Account Roles');

    return
      '<p class="aphront-form-instructions">'.
        'For a detailed explanation of account roles, see '.
        $roles_link.'.'.
      '</p>';
  }

}
