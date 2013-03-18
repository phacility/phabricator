<?php

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

    $crumbs = $this->buildApplicationCrumbs($this->buildSideNavView());
    if ($this->id) {
      $user = id(new PhabricatorUser())->load($this->id);
      if (!$user) {
        return new Aphront404Response();
      }
      $base_uri = '/people/edit/'.$user->getID().'/';
      $crumbs->addCrumb(
        id(new PhabricatorCrumbView())
          ->setName(pht('Edit User'))
          ->setHref('/people/edit/'));
      $crumbs->addCrumb(
        id(new PhabricatorCrumbView())
          ->setName($user->getFullName())
          ->setHref($base_uri));
    } else {
      $user = new PhabricatorUser();
      $base_uri = '/people/edit/';
      $crumbs->addCrumb(
        id(new PhabricatorCrumbView())
          ->setName(pht('Create New User'))
          ->setHref($base_uri));
    }

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($base_uri));
    $nav->addLabel(pht('User Information'));
    $nav->addFilter('basic', pht('Basic Information'));
    $nav->addFilter('role', pht('Edit Roles'));
    $nav->addFilter('cert', pht('Conduit Certificate'));
    $nav->addFilter('profile',
      pht('View Profile'), '/p/'.$user->getUsername().'/');
    $nav->addLabel(pht('Special'));
    $nav->addFilter('rename', pht('Change Username'));
    if ($user->getIsSystemAgent()) {
      $nav->addFilter('picture', pht('Set Account Picture'));
    }
    $nav->addFilter('delete', pht('Delete User'));

    if (!$user->getID()) {
      $this->view = 'basic';
    }

    $view = $nav->selectFilter($this->view, 'basic');

    $content = array();

    if ($request->getStr('saved')) {
      $notice = new AphrontErrorView();
      $notice->setSeverity(AphrontErrorView::SEVERITY_NOTICE);
      $notice->setTitle(pht('Changes Saved'));
      $notice->appendChild(
        phutil_tag('p', array(), pht('Your changes were saved.')));
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
      case 'picture':
        $response = $this->processSetAccountPicture($user);
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
    } else {
      $nav = $this->buildSideNavView();
      $nav->selectFilter('edit');
      $nav->appendChild($content);
    }

    $nav->setCrumbs($crumbs);
    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => pht('Edit User'),
        'device' => true,
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
      $is_new = !$user->getID();

      if ($is_new) {
        $user->setUsername($request->getStr('username'));

        $new_email = $request->getStr('email');
        if (!strlen($new_email)) {
          $errors[] = pht('Email is required.');
          $e_email = pht('Required');
        } else if (!PhabricatorUserEmail::isAllowedAddress($new_email)) {
          $e_email = pht('Invalid');
          $errors[] = PhabricatorUserEmail::describeAllowedAddresses();
        } else {
          $e_email = null;
        }

      }
      $user->setRealName($request->getStr('realname'));

      if (!strlen($user->getUsername())) {
        $errors[] = pht("Username is required.");
        $e_username = pht('Required');
      } else if (!PhabricatorUser::validateUsername($user->getUsername())) {
        $errors[] = PhabricatorUser::describeValidUsername();
        $e_username = pht('Invalid');
      } else {
        $e_username = null;
      }

      if (!strlen($user->getRealName())) {
        $errors[] = pht('Real name is required.');
        $e_realname = pht('Required');
      } else {
        $e_realname = null;
      }

      if (!$errors) {
        try {

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

            if ($request->getStr('role') == 'agent') {
              id(new PhabricatorUserEditor())
                ->setActor($admin)
                ->makeSystemAgentUser($user, true);
            }

          }

          if ($welcome_checked) {
            $user->sendWelcomeEmail($admin);
          }

          $response = id(new AphrontRedirectResponse())
            ->setURI('/people/edit/'.$user->getID().'/?saved=true');
          return $response;
        } catch (AphrontQueryDuplicateKeyException $ex) {
          $errors[] = pht('Username and email must be unique.');

          $same_username = id(new PhabricatorUser())
            ->loadOneWhere('username = %s', $user->getUsername());
          $same_email = id(new PhabricatorUserEmail())
            ->loadOneWhere('address = %s', $new_email);

          if ($same_username) {
            $e_username = pht('Duplicate');
          }

          if ($same_email) {
            $e_email = pht('Duplicate');
          }
        }
      }
    }

    $error_view = null;
    if ($errors) {
      $error_view = id(new AphrontErrorView())
        ->setTitle(pht('Form Errors'))
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
          ->setLabel(pht('Username'))
          ->setName('username')
          ->setValue($user->getUsername())
          ->setError($e_username)
          ->setDisabled($is_immutable))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Real Name'))
          ->setName('realname')
          ->setValue($user->getRealName())
          ->setError($e_realname));

    if (!$user->getID()) {
      $form->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Email'))
          ->setName('email')
          ->setDisabled($is_immutable)
          ->setValue($new_email)
          ->setCaption(PhabricatorUserEmail::describeAllowedAddresses())
          ->setError($e_email));
    } else {
      $email = $user->loadPrimaryEmail();
      if ($email) {
        $status = $email->getIsVerified() ?
          pht('Verified') : pht('Unverified');
      } else {
        $status = pht('No Email Address');
      }

      $form->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel(pht('Email'))
          ->setValue($status));

      $form->appendChild(
        id(new AphrontFormCheckboxControl())
        ->addCheckbox(
          'welcome',
          1,
          pht('Re-send "Welcome to Phabricator" email.'),
          false));

    }

    $form->appendChild($this->getRoleInstructions());

    if (!$user->getID()) {
      $form
        ->appendChild(
          id(new AphrontFormSelectControl())
            ->setLabel(pht('Role'))
            ->setName('role')
            ->setValue('user')
            ->setOptions(
              array(
                'user'  => pht('Normal User'),
                'agent' => pht('System Agent'),
              ))
            ->setCaption(
              pht('You can create a "system agent" account for bots, '.
              'scripts, etc.')))
        ->appendChild(
          id(new AphrontFormCheckboxControl())
            ->addCheckbox(
              'welcome',
              1,
              pht('Send "Welcome to Phabricator" email.'),
              $welcome_checked));
    } else {
      $roles = array();

      if ($user->getIsSystemAgent()) {
        $roles[] = pht('System Agent');
      }
      if ($user->getIsAdmin()) {
        $roles[] = pht('Admin');
      }
      if ($user->getIsDisabled()) {
        $roles[] = pht('Disabled');
      }

      if (!$roles) {
        $roles[] = pht('Normal User');
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
          ->setValue(pht('Save')));

    $panel = new AphrontPanelView();
    if ($user->getID()) {
      $panel->setHeader(pht('Edit User'));
    } else {
      $panel->setHeader(pht('Create New User'));
    }

    $panel->appendChild($form);
    $panel->setNoBackground();
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
        $errors[] = pht("You can not edit your own role.");
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
        ->setTitle(pht('Form Errors'))
        ->setErrors($errors);
    }


    $form = id(new AphrontFormView())
      ->setUser($admin)
      ->setAction($request->getRequestURI()->alter('saved', null));

    if ($is_self) {
      $inst = pht('NOTE: You can not edit your own role.');
      $form->appendChild(hsprintf(
        '<p class="aphront-form-instructions">%s</p>', $inst));
    }

    $form
      ->appendChild($this->getRoleInstructions())
      ->appendChild(
        id(new AphrontFormCheckboxControl())
          ->addCheckbox(
            'is_admin',
            1,
            pht('Administrator'),
            $user->getIsAdmin())
          ->setDisabled($is_self))
      ->appendChild(
        id(new AphrontFormCheckboxControl())
          ->addCheckbox(
            'is_disabled',
            1,
            pht('Disabled'),
            $user->getIsDisabled())
          ->setDisabled($is_self))
      ->appendChild(
        id(new AphrontFormCheckboxControl())
          ->addCheckbox(
            'is_agent',
            1,
            pht('System Agent (Bot/Script User)'),
            $user->getIsSystemAgent())
          ->setDisabled(true));

    if (!$is_self) {
      $form
        ->appendChild(
          id(new AphrontFormSubmitControl())
            ->setValue(pht('Edit Role')));
    }

    $panel = new AphrontPanelView();
    $panel->setHeader(pht('Edit Role'));
    $panel->setWidth(AphrontPanelView::WIDTH_FORM);
    $panel->setNoBackground();
    $panel->appendChild($form);

    return array($error_view, $panel);
  }

  private function processCertificateRequest($user) {
    $request = $this->getRequest();
    $admin = $request->getUser();

    $inst = pht('You can use this certificate '.
        'to write scripts or bots which interface with Phabricator over '.
        'Conduit.');
    $form = new AphrontFormView();
    $form
      ->setUser($admin)
      ->setAction($request->getRequestURI())
      ->appendChild(hsprintf(
        '<p class="aphront-form-instructions">%s</p>', $inst));

    if ($user->getIsSystemAgent()) {
      $form
        ->appendChild(
          id(new AphrontFormTextControl())
            ->setLabel(pht('Username'))
            ->setValue($user->getUsername()))
        ->appendChild(
          id(new AphrontFormTextAreaControl())
            ->setLabel(pht('Certificate'))
            ->setValue($user->getConduitCertificate()));
    } else {
      $form->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel(pht('Certificate'))
          ->setValue(
            pht('You may only view the certificates of System Agents.')));
    }

    $panel = new AphrontPanelView();
    $panel->setHeader(pht('Conduit Certificate'));
    $panel->setWidth(AphrontPanelView::WIDTH_FORM);
    $panel->setNoBackground();
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
        $e_username = pht('Required');
        $errors[] = pht('New username is required.');
      } else if ($username == $user->getUsername()) {
        $e_username = pht('Invalid');
        $errors[] = pht('New username must be different from old username.');
      } else if (!PhabricatorUser::validateUsername($username)) {
        $e_username = pht('Invalid');
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
          $e_username = pht('Not Unique');
          $errors[] = pht('Another user already has that username.');
        }
      }
    }

    if ($errors) {
      $errors = id(new AphrontErrorView())
        ->setTitle(pht('Form Errors'))
        ->setErrors($errors);
    } else {
      $errors = null;
    }

    $inst1 = pht('Be careful when renaming users!');
    $inst2 = pht('The old username will no longer be tied to the user, so '.
          'anything which uses it (like old commit messages) will no longer '.
          'associate correctly. And if you give a user a username which some '.
          'other user used to have, username lookups will begin returning '.
          'the wrong user.');
    $inst3 = pht('It is generally safe to rename newly created users (and '.
          'test users and so on), but less safe to rename established users '.
          'and unsafe to reissue a username.');
    $inst4 = pht('Users who rely on password auth will need to reset their '.
          'passwordafter their username is changed (their username is part '.
          'of the salt in the password hash). They will receive an email '.
          'with instructions on how to do this.');

    $form = new AphrontFormView();
    $form
      ->setUser($admin)
      ->setAction($request->getRequestURI())
      ->appendChild(hsprintf(
        '<p class="aphront-form-instructions">'.
          '<strong>%s</strong> '.
          '%s'.
        '</p>'.
        '<p class="aphront-form-instructions">'.
          '%s'.
        '</p>'.
        '<p class="aphront-form-instructions">'.
          '%s'.
        '</p>', $inst1, $inst2, $inst3, $inst4))
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel(pht('Old Username'))
          ->setValue($user->getUsername()))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('New Username'))
          ->setValue($username)
          ->setName('username')
          ->setError($e_username))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Change Username')));

    $panel = new AphrontPanelView();
    $panel->setHeader(pht('Change Username'));
    $panel->setWidth(AphrontPanelView::WIDTH_FORM);
    $panel->setNoBackground();
    $panel->appendChild($form);

    return array($errors, $panel);
  }

  private function processDeleteRequest(PhabricatorUser $user) {
    $request = $this->getRequest();
    $admin = $request->getUser();

    $far1 = pht('As you stare into the gaping maw of the abyss, something '.
        'hold you back.');
    $far2 = pht('You can not delete your own account.');

    if ($user->getPHID() == $admin->getPHID()) {
      $error = new AphrontErrorView();
      $error->setTitle(pht('You Shall Journey No Farther'));
      $error->appendChild(hsprintf(
        '<p>%s</p><p>%s</p>', $far1, $far2));
      return $error;
    }

    $e_username = true;
    $username = null;

    $errors = array();
    if ($request->isFormPost()) {

      $username = $request->getStr('username');
      if (!strlen($username)) {
        $e_username = pht('Required');
        $errors[] = pht('You must type the username to confirm deletion.');
      } else if ($username != $user->getUsername()) {
        $e_username = pht('Invalid');
        $errors[] = pht('You must type the username correctly.');
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
        ->setTitle(pht('Form Errors'))
        ->setErrors($errors);
    } else {
      $errors = null;
    }

    $str1 = pht('Be careful when deleting users!');
    $str2 = pht('If this user interacted with anything, it is generally '.
        'better to disable them, not delete them. If you delete them, it will '.
        'no longer be possible to search for their objects, for example, '.
        'and you will lose other information about their history. Disabling '.
        'them instead will prevent them from logging in but not destroy '.
        'any of their data.');
    $str3 = pht('It is generally safe to delete newly created users (and '.
          'test users and so on), but less safe to delete established users. '.
          'If possible, disable them instead.');

    $form = new AphrontFormView();
    $form
      ->setUser($admin)
      ->setAction($request->getRequestURI())
      ->appendChild(hsprintf(
        '<p class="aphront-form-instructions">'.
          '<strong>%s</strong> %s'.
        '</p>'.
        '<p class="aphront-form-instructions">'.
          '%s'.
        '</p>', $str1, $str2, $str3))
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel(pht('Username'))
          ->setValue($user->getUsername()))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Confirm'))
          ->setValue($username)
          ->setName('username')
          ->setCaption(pht("Type the username again to confirm deletion."))
          ->setError($e_username))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Delete User')));

    $panel = new AphrontPanelView();
    $panel->setHeader(pht('Delete User'));
    $panel->setWidth(AphrontPanelView::WIDTH_FORM);
    $panel->setNoBackground();
    $panel->appendChild($form);

    return array($errors, $panel);
  }

  private function getRoleInstructions() {
    $roles_link = phutil_tag(
      'a',
      array(
        'href'   => PhabricatorEnv::getDoclink(
          'article/User_Guide_Account_Roles.html'),
        'target' => '_blank',
      ),
      pht('User Guide: Account Roles'));

    $inst = pht('For a detailed explanation of account roles, see %s.',
      $roles_link);
    return hsprintf(
      '<p class="aphront-form-instructions">%s</p>',
      $inst);
  }

  private function processSetAccountPicture(PhabricatorUser $user) {
    $request = $this->getRequest();
    $admin = $request->getUser();

    $profile = id(new PhabricatorUserProfile())->loadOneWhere(
      'userPHID = %s',
      $user->getPHID());
    if (!$profile) {
      $profile = new PhabricatorUserProfile();
      $profile->setUserPHID($user->getPHID());
      $profile->setTitle('');
      $profile->setBlurb('');
    }



    $supported_formats = PhabricatorFile::getTransformableImageFormats();

    $e_image = null;
    $errors = array();

    if ($request->isFormPost()) {
      $default_image = $request->getExists('default_image');

      if ($default_image) {
        $profile->setProfileImagePHID(null);
        $user->setProfileImagePHID(null);
      } else if ($request->getFileExists('image')) {
        $file = null;
        $file = PhabricatorFile::newFromPHPUpload(
          $_FILES['image'],
          array(
            'authorPHID' => $admin->getPHID(),
          ));

        $okay = $file->isTransformableImage();

        if ($okay) {
          $xformer = new PhabricatorImageTransformer();

          // Generate the large picture for the profile page.
          $large_xformed = $xformer->executeProfileTransform(
            $file,
            $width = 280,
            $min_height = 140,
            $max_height = 420);
          $profile->setProfileImagePHID($large_xformed->getPHID());

          // Generate the small picture for comments, etc.
          $small_xformed = $xformer->executeProfileTransform(
            $file,
            $width = 50,
            $min_height = 50,
            $max_height = 50);
          $user->setProfileImagePHID($small_xformed->getPHID());
        } else {
          $e_image = pht('Not Supported');
          $errors[] =
            pht('This server only supports these image formats:').
              ' ' .implode(', ', $supported_formats);
        }
      }

     if (!$errors) {
       $user->save();
       $profile->save();
        $response = id(new AphrontRedirectResponse())
          ->setURI('/people/edit/'.$user->getID().'/picture/');
        return $response;
      }
    }


    $error_view = null;
    if ($errors) {
      $error_view = new AphrontErrorView();
      $error_view->setTitle(pht('Form Errors'));
      $error_view->setErrors($errors);
    } else {
      if ($request->getStr('saved')) {
        $error_view = new AphrontErrorView();
        $error_view->setSeverity(AphrontErrorView::SEVERITY_NOTICE);
        $error_view->setTitle(pht('Changes Saved'));
        $error_view->appendChild(
          phutil_tag('p', array(), pht('Your changes have been saved.')));
        $error_view = $error_view->render();
      }
    }

    $img_src = $user->loadProfileImageURI();

    $form = new AphrontFormView();
    $form
      ->setUser($admin)
      ->setAction($request->getRequestURI())
      ->setEncType('multipart/form-data')
      ->appendChild(
        id(new AphrontFormMarkupControl())
          ->setLabel(pht('Profile Image'))
          ->setValue(
            phutil_tag(
              'img',
              array(
                'src' => $img_src,
              ))))
      ->appendChild(
        id(new AphrontFormImageControl())
          ->setLabel(pht('Change Image'))
          ->setName('image')
          ->setError($e_image)
          ->setCaption(
            pht('Supported formats: %s', implode(', ', $supported_formats))));

      $form->appendChild(
      id(new AphrontFormSubmitControl())
        ->setValue(pht('Save'))
        ->addCancelButton('/people/edit/'.$user->getID().'/'));

    $panel = new AphrontPanelView();
    $panel->setHeader(pht('Set Profile Picture'));
    $panel->setWidth(AphrontPanelView::WIDTH_FORM);
    $panel->setNoBackground();
    $panel->appendChild($form);

    return array($error_view, $panel);

  }

}
