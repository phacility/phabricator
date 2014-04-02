<?php

final class PhabricatorPeopleEditController
  extends PhabricatorPeopleController {

  public function processRequest() {

    $request = $this->getRequest();
    $admin = $request->getUser();

    $crumbs = $this->buildApplicationCrumbs($this->buildSideNavView());

    $user = new PhabricatorUser();
    $base_uri = '/people/edit/';
    $crumbs->addTextCrumb(pht('Create New User'), $base_uri);

    $content = array();

    $response = $this->processBasicRequest($user);
    if ($response instanceof AphrontResponse) {
      return $response;
    }

    $content[] = $response;

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $content,
      ),
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

          if ($welcome_checked) {
            $user->sendWelcomeEmail($admin);
          }

          $response = id(new AphrontRedirectResponse())
            ->setURI('/p/'.$user->getUsername().'/');
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
    $form->setAction('/people/edit/');

    $is_immutable = false;

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

    $form->appendChild(
      id(new AphrontFormTextControl())
        ->setLabel(pht('Email'))
        ->setName('email')
        ->setDisabled($is_immutable)
        ->setValue($new_email)
        ->setCaption(PhabricatorUserEmail::describeAllowedAddresses())
        ->setError($e_email));

    $form->appendChild($this->getRoleInstructions());

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

    $form
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->addCancelButton($this->getApplicationURI())
          ->setValue(pht('Save')));

    $title = pht('Create New User');

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText($title)
      ->setFormErrors($errors)
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
