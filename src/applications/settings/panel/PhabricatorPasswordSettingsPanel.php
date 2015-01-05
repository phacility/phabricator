<?php

final class PhabricatorPasswordSettingsPanel extends PhabricatorSettingsPanel {

  public function getPanelKey() {
    return 'password';
  }

  public function getPanelName() {
    return pht('Password');
  }

  public function getPanelGroup() {
    return pht('Authentication');
  }

  public function isEnabled() {
    // There's no sense in showing a change password panel if this install
    // doesn't support password authentication.
    if (!PhabricatorPasswordAuthProvider::getPasswordProvider()) {
      return false;
    }

    return true;
  }

  public function processRequest(AphrontRequest $request) {
    $user = $request->getUser();

    $token = id(new PhabricatorAuthSessionEngine())->requireHighSecuritySession(
      $user,
      $request,
      '/settings/');

    $min_len = PhabricatorEnv::getEnvConfig('account.minimum-password-length');
    $min_len = (int)$min_len;

    // NOTE: To change your password, you need to prove you own the account,
    // either by providing the old password or by carrying a token to
    // the workflow from a password reset email.

    $key = $request->getStr('key');
    $token = null;
    if ($key) {
      $token = id(new PhabricatorAuthTemporaryTokenQuery())
        ->setViewer($user)
        ->withObjectPHIDs(array($user->getPHID()))
        ->withTokenTypes(
          array(PhabricatorAuthSessionEngine::PASSWORD_TEMPORARY_TOKEN_TYPE))
        ->withTokenCodes(array(PhabricatorHash::digest($key)))
        ->withExpired(false)
        ->executeOne();
    }

    $e_old = true;
    $e_new = true;
    $e_conf = true;

    $errors = array();
    if ($request->isFormPost()) {
      if (!$token) {
        $envelope = new PhutilOpaqueEnvelope($request->getStr('old_pw'));
        if (!$user->comparePassword($envelope)) {
          $errors[] = pht('The old password you entered is incorrect.');
          $e_old = pht('Invalid');
        }
      }

      $pass = $request->getStr('new_pw');
      $conf = $request->getStr('conf_pw');

      if (strlen($pass) < $min_len) {
        $errors[] = pht('Your new password is too short.');
        $e_new = pht('Too Short');
      } else if ($pass !== $conf) {
        $errors[] = pht('New password and confirmation do not match.');
        $e_conf = pht('Invalid');
      } else if (PhabricatorCommonPasswords::isCommonPassword($pass)) {
        $e_new = pht('Very Weak');
        $e_conf = pht('Very Weak');
        $errors[] = pht(
          'Your new password is very weak: it is one of the most common '.
          'passwords in use. Choose a stronger password.');
      }

      if (!$errors) {
        // This write is unguarded because the CSRF token has already
        // been checked in the call to $request->isFormPost() and
        // the CSRF token depends on the password hash, so when it
        // is changed here the CSRF token check will fail.
        $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();

          $envelope = new PhutilOpaqueEnvelope($pass);
          id(new PhabricatorUserEditor())
            ->setActor($user)
            ->changePassword($user, $envelope);

        unset($unguarded);

        if ($token) {
          // Destroy the token.
          $token->delete();

          // If this is a password set/reset, kick the user to the home page
          // after we update their account.
          $next = '/';
        } else {
          $next = $this->getPanelURI('?saved=true');
        }

        id(new PhabricatorAuthSessionEngine())->terminateLoginSessions(
          $user,
          $request->getCookie(PhabricatorCookies::COOKIE_SESSION));

        return id(new AphrontRedirectResponse())->setURI($next);
      }
    }

    $hash_envelope = new PhutilOpaqueEnvelope($user->getPasswordHash());
    if (strlen($hash_envelope->openEnvelope())) {
      try {
        $can_upgrade = PhabricatorPasswordHasher::canUpgradeHash(
          $hash_envelope);
      } catch (PhabricatorPasswordHasherUnavailableException $ex) {
        $can_upgrade = false;

        // Only show this stuff if we aren't on the reset workflow. We can
        // do resets regardless of the old hasher's availability.
        if (!$token) {
          $errors[] = pht(
            'Your password is currently hashed using an algorithm which is '.
            'no longer available on this install.');
          $errors[] = pht(
            'Because the algorithm implementation is missing, your password '.
            'can not be used or updated.');
          $errors[] = pht(
            'To set a new password, request a password reset link from the '.
            'login screen and then follow the instructions.');
        }
      }

      if ($can_upgrade) {
        $errors[] = pht(
          'The strength of your stored password hash can be upgraded. '.
          'To upgrade, either: log out and log in using your password; or '.
          'change your password.');
      }
    }

    $len_caption = null;
    if ($min_len) {
      $len_caption = pht('Minimum password length: %d characters.', $min_len);
    }

    $form = new AphrontFormView();
    $form
      ->setUser($user)
      ->addHiddenInput('key', $key);

    if (!$token) {
      $form->appendChild(
        id(new AphrontFormPasswordControl())
          ->setLabel(pht('Old Password'))
          ->setError($e_old)
          ->setName('old_pw'));
    }

    $form
      ->appendChild(
        id(new AphrontFormPasswordControl())
          ->setDisableAutocomplete(true)
          ->setLabel(pht('New Password'))
          ->setError($e_new)
          ->setName('new_pw'));
    $form
      ->appendChild(
        id(new AphrontFormPasswordControl())
          ->setDisableAutocomplete(true)
          ->setLabel(pht('Confirm Password'))
          ->setCaption($len_caption)
          ->setError($e_conf)
          ->setName('conf_pw'));
    $form
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Change Password')));

    $form->appendChild(
      id(new AphrontFormStaticControl())
        ->setLabel(pht('Current Algorithm'))
        ->setValue(PhabricatorPasswordHasher::getCurrentAlgorithmName(
          new PhutilOpaqueEnvelope($user->getPasswordHash()))));

    $form->appendChild(
      id(new AphrontFormStaticControl())
        ->setLabel(pht('Best Available Algorithm'))
        ->setValue(PhabricatorPasswordHasher::getBestAlgorithmName()));

    $form->appendRemarkupInstructions(
      pht(
        'NOTE: Changing your password will terminate any other outstanding '.
        'login sessions.'));

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Change Password'))
      ->setFormSaved($request->getStr('saved'))
      ->setFormErrors($errors)
      ->setForm($form);

    return array(
      $form_box,
    );
  }


}
