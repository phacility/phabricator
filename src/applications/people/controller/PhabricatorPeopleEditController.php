<?php

final class PhabricatorPeopleEditController
  extends PhabricatorPeopleController {

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
      $crumbs->addTextCrumb(pht('Edit User'), '/people/edit/');
      $crumbs->addTextCrumb($user->getFullName(), $base_uri);
    } else {
      $user = new PhabricatorUser();
      $base_uri = '/people/edit/';
      $crumbs->addTextCrumb(pht('Create New User'), $base_uri);
    }

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($base_uri));
    $nav->addLabel(pht('User Information'));
    $nav->addFilter('basic', pht('Basic Information'));
    $nav->addFilter('cert', pht('Conduit Certificate'));
    $nav->addFilter('profile',
      pht('View Profile'), '/p/'.$user->getUsername().'/');

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
      case 'cert':
        $response = $this->processCertificateRequest($user);
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

            // Automatically approve the user, since an admin is creating them.
            $user->setIsApproved(1);

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
      if (!$user->getIsApproved()) {
        $roles[] = pht('Not Approved');
      }
      if (!$roles) {
        $roles[] = pht('Normal User');
      }

      $roles = implode(', ', $roles);

      $form->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel(pht('Roles'))
          ->setValue($roles));
    }

    $form
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Save')));

    if ($user->getID()) {
      $title = pht('Edit User');
    } else {
      $title = pht('Create New User');
    }

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText($title)
      ->setFormErrors($errors)
      ->setForm($form);

    return array($form_box);
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
      ->appendChild(
        phutil_tag('p', array('class' => 'aphront-form-instructions'), $inst));

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

    $title = pht('Conduit Certificate');

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText($title)
      ->setForm($form);

    return array($form_box);
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

    return phutil_tag(
      'p',
      array('class' => 'aphront-form-instructions'),
      pht('For a detailed explanation of account roles, see %s.', $roles_link));
  }

}
