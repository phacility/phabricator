<?php

final class PhabricatorPasswordSettingsPanel extends PhabricatorSettingsPanel {

  public function getPanelKey() {
    return 'password';
  }

  public function getPanelName() {
    return pht('Password');
  }

  public function getPanelGroupKey() {
    return PhabricatorSettingsAuthenticationPanelGroup::PANELGROUPKEY;
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

    // NOTE: Users can also change passwords through the separate "set/reset"
    // interface which is reached by logging in with a one-time token after
    // registration or password reset. If this flow changes, that flow may
    // also need to change.

    $e_old = true;
    $e_new = true;
    $e_conf = true;

    $errors = array();
    if ($request->isFormPost()) {
      $envelope = new PhutilOpaqueEnvelope($request->getStr('old_pw'));
      if (!$user->comparePassword($envelope)) {
        $errors[] = pht('The old password you entered is incorrect.');
        $e_old = pht('Invalid');
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

        $next = $this->getPanelURI('?saved=true');

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

    $form = id(new AphrontFormView())
      ->setViewer($user)
      ->appendChild(
        id(new AphrontFormPasswordControl())
          ->setLabel(pht('Old Password'))
          ->setError($e_old)
          ->setName('old_pw'))
      ->appendChild(
        id(new AphrontFormPasswordControl())
          ->setDisableAutocomplete(true)
          ->setLabel(pht('New Password'))
          ->setError($e_new)
          ->setName('new_pw'))
      ->appendChild(
        id(new AphrontFormPasswordControl())
          ->setDisableAutocomplete(true)
          ->setLabel(pht('Confirm Password'))
          ->setCaption($len_caption)
          ->setError($e_conf)
          ->setName('conf_pw'))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Change Password')));

    $properties = id(new PHUIPropertyListView());

    $properties->addProperty(
      pht('Current Algorithm'),
      PhabricatorPasswordHasher::getCurrentAlgorithmName(
        new PhutilOpaqueEnvelope($user->getPasswordHash())));

    $properties->addProperty(
      pht('Best Available Algorithm'),
      PhabricatorPasswordHasher::getBestAlgorithmName());

    $info_view = id(new PHUIInfoView())
      ->setSeverity(PHUIInfoView::SEVERITY_NOTICE)
      ->appendChild(
        pht('Changing your password will terminate any other outstanding '.
            'login sessions.'));

    $algo_box = $this->newBox(pht('Password Algorithms'), $properties);
    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Change Password'))
      ->setFormSaved($request->getStr('saved'))
      ->setFormErrors($errors)
      ->setBackground(PHUIObjectBoxView::WHITE_CONFIG)
      ->setForm($form);

    return array(
      $form_box,
      $algo_box,
      $info_view,
    );
  }


}
