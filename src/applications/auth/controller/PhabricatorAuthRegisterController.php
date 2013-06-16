<?php

final class PhabricatorAuthRegisterController
  extends PhabricatorAuthController {

  private $accountKey;
  private $account;
  private $provider;

  public function shouldRequireLogin() {
    return false;
  }

  public function willProcessRequest(array $data) {
    $this->accountKey = idx($data, 'akey');
  }

  public function processRequest() {
    $request = $this->getRequest();

    if ($request->getUser()->isLoggedIn()) {
      return $this->renderError(pht('You are already logged in.'));
    }

    if (strlen($this->accountKey)) {
      $response = $this->loadAccount();
    } else {
      $response = $this->loadDefaultAccount();
    }

    if ($response) {
      return $response;
    }

    $account = $this->account;

    $user = new PhabricatorUser();

    $default_username = $account->getUsername();
    $default_realname = $account->getRealName();
    $default_email = $account->getEmail();
    if ($default_email) {
      // If the account source provided an email but it's not allowed by
      // the configuration, just pretend we didn't get an email at all.
      if (!PhabricatorUserEmail::isAllowedAddress($default_email)) {
        $default_email = null;
      }

      // If the account source provided an email, but another account already
      // has that email, just pretend we didn't get an email.

      // TODO: See T3340.

      if ($default_email) {
        $same_email = id(new PhabricatorUserEmail())->loadOneWhere(
          'address = %s',
          $default_email);
        if ($same_email) {
          $default_email = null;
        }
      }
    }

    $profile = id(new PhabricatorRegistrationProfile())
      ->setDefaultUsername($default_username)
      ->setDefaultEmail($default_email)
      ->setDefaultRealName($default_realname)
      ->setCanEditUsername(true)
      ->setCanEditEmail(($default_email === null))
      ->setCanEditRealName(true)
      ->setShouldVerifyEmail(false);

    $event_type = PhabricatorEventType::TYPE_AUTH_WILLREGISTERUSER;
    $event_data = array(
      'account' => $account,
      'profile' => $profile,
    );

    $event = id(new PhabricatorEvent($event_type, $event_data))
      ->setUser($user);
    PhutilEventEngine::dispatchEvent($event);

    $default_username = $profile->getDefaultUsername();
    $default_email = $profile->getDefaultEmail();
    $default_realname = $profile->getDefaultRealName();

    $can_edit_username = $profile->getCanEditUsername();
    $can_edit_email = $profile->getCanEditEmail();
    $can_edit_realname = $profile->getCanEditRealName();

    $must_set_password = $this->provider->shouldRequireRegistrationPassword();

    $can_edit_anything = $profile->getCanEditAnything() || $must_set_password;
    $force_verify = $profile->getShouldVerifyEmail();

    $value_username = $default_username;
    $value_realname = $default_realname;
    $value_email = $default_email;
    $value_password = null;

    $errors = array();

    $e_username = strlen($value_username) ? null : true;
    $e_realname = strlen($value_realname) ? null : true;
    $e_email = strlen($value_email) ? null : true;
    $e_password = true;

    $min_len = PhabricatorEnv::getEnvConfig('account.minimum-password-length');
    $min_len = (int)$min_len;

    if ($request->isFormPost() || !$can_edit_anything) {
      $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();

      if ($can_edit_username) {
        $value_username = $request->getStr('username');
        if (!strlen($value_username)) {
          $e_username = pht('Required');
          $errors[] = pht('Username is required.');
        } else if (!PhabricatorUser::validateUsername($value_username)) {
          $e_username = pht('Invalid');
          $errors[] = PhabricatorUser::describeValidUsername();
        } else {
          $e_username = null;
        }
      }

      if ($must_set_password) {
        $value_password = $request->getStr('password');
        $value_confirm = $request->getStr('confirm');
        if (!strlen($value_password)) {
          $e_password = pht('Required');
          $errors[] = pht('You must choose a password.');
        } else if ($value_password !== $value_confirm) {
          $e_password = pht('No Match');
          $errors[] = pht('Password and confirmation must match.');
        } else if (strlen($value_password) < $min_len) {
          $e_password = pht('Too Short');
          $errors[] = pht(
            'Password is too short (must be at least %d characters long).',
            $min_len);
        } else {
          $e_password = null;
        }
      }

      if ($can_edit_email) {
        $value_email = $request->getStr('email');
        if (!strlen($value_email)) {
          $e_email = pht('Required');
          $errors[] = pht('Email is required.');
        } else if (!PhabricatorUserEmail::isAllowedAddress($value_email)) {
          $e_email = pht('Invalid');
          $errors[] = PhabricatorUserEmail::describeAllowedAddresses();
        } else {
          $e_email = null;
        }
      }

      if ($can_edit_realname) {
        $value_realname = $request->getStr('realName');
        if (!strlen($value_realname)) {
          $e_realname = pht('Required');
          $errors[] = pht('Real name is required.');
        } else {
          $e_realname = null;
        }
      }

      if (!$errors) {
        $image = $this->loadProfilePicture($account);
        if ($image) {
          $user->setProfileImagePHID($image->getPHID());
        }

        try {
          if ($force_verify) {
            $verify_email = true;
          } else {
            $verify_email =
              ($account->getEmailVerified()) &&
              ($value_email === $default_email);
          }

          $email_obj = id(new PhabricatorUserEmail())
            ->setAddress($value_email)
            ->setIsVerified((int)$verify_email);

          $user->setUsername($value_username);
          $user->setRealname($value_realname);

          $user->openTransaction();

            $editor = id(new PhabricatorUserEditor())
              ->setActor($user);

            $editor->createNewUser($user, $email_obj);
            if ($must_set_password) {
              $envelope = new PhutilOpaqueEnvelope($value_password);
              $editor->changePassword($user, $envelope);
            }

            $account->setUserPHID($user->getPHID());
            $this->provider->willRegisterAccount($account);
            $account->save();

          $user->saveTransaction();

          $this->establishWebSession($user);

          if (!$email_obj->getIsVerified()) {
            $email_obj->sendVerificationEmail($user);
          }

          return $this->buildLoginValidateResponse($user);
        } catch (AphrontQueryDuplicateKeyException $exception) {
          $same_username = id(new PhabricatorUser())->loadOneWhere(
            'userName = %s',
            $user->getUserName());

          $same_email = id(new PhabricatorUserEmail())->loadOneWhere(
            'address = %s',
            $value_email);

          if ($same_username) {
            $e_username = pht('Duplicate');
            $errors[] = pht('Another user already has that username.');
          }

          if ($same_email) {
            // TODO: See T3340.
            $e_email = pht('Duplicate');
            $errors[] = pht('Another user already has that email.');
          }

          if (!$same_username && !$same_email) {
            throw $exception;
          }
        }
      }

      unset($unguarded);
    }

    $error_view = null;
    if ($errors) {
      $error_view = new AphrontErrorView();
      $error_view->setTitle(pht('Registration Failed'));
      $error_view->setErrors($errors);
    }

    $form = id(new AphrontFormView())
      ->setUser($request->getUser())
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Username'))
          ->setName('username')
          ->setValue($value_username)
          ->setError($e_username));

    if ($must_set_password) {
      $form->appendChild(
        id(new AphrontFormPasswordControl())
          ->setLabel(pht('Password'))
          ->setName('password')
          ->setError($e_password)
          ->setCaption(
            $min_len
              ? pht('Minimum length of %d characters.', $min_len)
              : null));
      $form->appendChild(
        id(new AphrontFormPasswordControl())
          ->setLabel(pht('Confirm Password'))
          ->setName('confirm')
          ->setError($e_password));
    }

    if ($can_edit_email) {
      $form->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Email'))
          ->setName('email')
          ->setValue($value_email)
          ->setCaption(PhabricatorUserEmail::describeAllowedAddresses())
          ->setError($e_email));
    }

    if ($can_edit_realname) {
      $form->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Real Name'))
          ->setName('realName')
          ->setValue($value_realname)
          ->setError($e_realname));
    }

    $form->appendChild(
      id(new AphrontFormSubmitControl())
        ->setValue(pht('Create Account')));

    $title = pht('Phabricator Registration');

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName(pht('Register')));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $error_view,
        $form,
      ),
      array(
        'title' => $title,
        'device' => true,
        'dust' => true,
      ));
  }

  private function loadAccount() {
    $request = $this->getRequest();

    if (!$this->accountKey) {
      return $this->renderError(pht('Request did not include account key.'));
    }

    $account = id(new PhabricatorExternalAccount())->loadOneWhere(
      'accountSecret = %s',
      $this->accountKey);

    if (!$account) {
      return $this->renderError(pht('No registration account.'));
    }

    if ($account->getUserPHID()) {
      return $this->renderError(
        pht(
          'The account you are attempting to register with is already '.
          'registered to another user.'));
    }

    $registration_key = $request->getCookie('phreg');

    // NOTE: This registration key check is not strictly necessary, because
    // we're only creating new accounts, not linking existing accounts. It
    // might be more hassle than it is worth, especially for email.
    //
    // The attack this prevents is getting to the registration screen, then
    // copy/pasting the URL and getting someone else to click it and complete
    // the process. They end up with an account bound to credentials you
    // control. This doesn't really let you do anything meaningful, though,
    // since you could have simply completed the process yourself.

    if (!$registration_key) {
      return $this->renderError(
        pht(
          'Your browser did not submit a registration key with the request. '.
          'You must use the same browser to begin and complete registration. '.
          'Check that cookies are enabled and try again.'));
    }

    // We store the digest of the key rather than the key itself to prevent a
    // theoretical attacker with read-only access to the database from
    // hijacking registration sessions.

    $actual = $account->getProperty('registrationKey');
    $expect = PhabricatorHash::digest($registration_key);
    if ($actual !== $expect) {
      return $this->renderError(
        pht(
          'Your browser submitted a different registration key than the one '.
          'associated with this account. You may need to clear your cookies.'));
    }

    $other_account = id(new PhabricatorExternalAccount())->loadAllWhere(
      'accountType = %s AND accountDomain = %s AND accountID = %s
        AND id != %d',
      $account->getAccountType(),
      $account->getAccountDomain(),
      $account->getAccountID(),
      $account->getID());

    if ($other_account) {
      return $this->renderError(
        pht(
          'The account you are attempting to register with already belongs '.
          'to another user.'));
    }

    $provider = PhabricatorAuthProvider::getEnabledProviderByKey(
      $account->getProviderKey());

    if (!$provider) {
      return $this->renderError(
        pht(
          'The account you are attempting to register with uses a nonexistent '.
          'or disabled authentication provider (with key "%s"). An '.
          'administrator may have recently disabled this provider.',
          $account->getProviderKey()));
    }

    if (!$provider->shouldAllowRegistration()) {

      // TODO: This is a routine error if you click "Login" on an external
      // auth source which doesn't allow registration. The error should be
      // more tailored.

      return $this->renderError(
        pht(
          'The account you are attempting to register with uses an '.
          'authentication provider ("%s") which does not allow registration. '.
          'An administrator may have recently disabled registration with this '.
          'provider.',
          $provider->getProviderName()));
    }

    $this->account = $account;
    $this->provider = $provider;

    return null;
  }

  private function loadDefaultAccount() {
    $providers = PhabricatorAuthProvider::getAllEnabledProviders();
    foreach ($providers as $key => $provider) {
      if (!$provider->shouldAllowRegistration()) {
        unset($providers[$key]);
        continue;
      }
      if (!$provider->isDefaultRegistrationProvider()) {
        unset($providers[$key]);
      }
    }

    if (!$providers) {
      return $this->renderError(
        pht(
          "There are no configured default registration providers."));
    } else if (count($providers) > 1) {
      return $this->renderError(
        pht(
          "There are too many configured default registration providers."));
    }

    $this->account = $provider->getDefaultExternalAccount();
    $this->provider = $provider;
    return null;
  }

  private function loadProfilePicture(PhabricatorExternalAccount $account) {
    $phid = $account->getProfileImagePHID();
    if (!$phid) {
      return null;
    }

    // NOTE: Use of omnipotent user is okay here because the registering user
    // can not control the field value, and we can't use their user object to
    // do meaningful policy checks anyway since they have not registered yet.
    // Reaching this means the user holds the account secret key and the
    // registration secret key, and thus has permission to view the image.

    $file = id(new PhabricatorFileQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withPHIDs(array($phid))
      ->executeOne();
    if (!$file) {
      return null;
    }

    try {
      $xformer = new PhabricatorImageTransformer();
      return $xformer->executeProfileTransform(
        $file,
        $width = 50,
        $min_height = 50,
        $max_height = 50);
    } catch (Exception $ex) {
      phlog($ex);
      return null;
    }
  }

  private function renderError($message) {
    return $this->renderErrorPage(
      pht('Registration Failed'),
      array($message));
  }

}
