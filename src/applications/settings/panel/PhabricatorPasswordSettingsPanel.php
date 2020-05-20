<?php

final class PhabricatorPasswordSettingsPanel extends PhabricatorSettingsPanel {

  public function getPanelKey() {
    return 'password';
  }

  public function getPanelName() {
    return pht('Password');
  }

  public function getPanelMenuIcon() {
    return 'fa-key';
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
    $viewer = $request->getUser();
    $user = $this->getUser();

    $content_source = PhabricatorContentSource::newFromRequest($request);

    $min_len = PhabricatorEnv::getEnvConfig('account.minimum-password-length');
    $min_len = (int)$min_len;

    // NOTE: Users can also change passwords through the separate "set/reset"
    // interface which is reached by logging in with a one-time token after
    // registration or password reset. If this flow changes, that flow may
    // also need to change.

    $account_type = PhabricatorAuthPassword::PASSWORD_TYPE_ACCOUNT;

    $password_objects = id(new PhabricatorAuthPasswordQuery())
      ->setViewer($viewer)
      ->withObjectPHIDs(array($user->getPHID()))
      ->withPasswordTypes(array($account_type))
      ->withIsRevoked(false)
      ->execute();
    if (!$password_objects) {
      return $this->newSetPasswordView($request);
    }
    $password_object = head($password_objects);

    $e_old = true;
    $e_new = true;
    $e_conf = true;

    $errors = array();
    if ($request->isFormOrHisecPost()) {
      $workflow_key = sprintf(
        'password.change(%s)',
        $user->getPHID());

      $hisec_token = id(new PhabricatorAuthSessionEngine())
        ->setWorkflowKey($workflow_key)
        ->requireHighSecurityToken($viewer, $request, '/settings/');

      // Rate limit guesses about the old password. This page requires MFA and
      // session compromise already, so this is mostly just to stop researchers
      // from reporting this as a vulnerability.
      PhabricatorSystemActionEngine::willTakeAction(
        array($viewer->getPHID()),
        new PhabricatorAuthChangePasswordAction(),
        1);

      $envelope = new PhutilOpaqueEnvelope($request->getStr('old_pw'));

      $engine = id(new PhabricatorAuthPasswordEngine())
        ->setViewer($viewer)
        ->setContentSource($content_source)
        ->setPasswordType($account_type)
        ->setObject($user);

      if (!strlen($envelope->openEnvelope())) {
        $errors[] = pht('You must enter your current password.');
        $e_old = pht('Required');
      } else if (!$engine->isValidPassword($envelope)) {
        $errors[] = pht('The old password you entered is incorrect.');
        $e_old = pht('Invalid');
      } else {
        $e_old = null;

        // Refund the user an action credit for getting the password right.
        PhabricatorSystemActionEngine::willTakeAction(
          array($viewer->getPHID()),
          new PhabricatorAuthChangePasswordAction(),
          -1);
      }

      $pass = $request->getStr('new_pw');
      $conf = $request->getStr('conf_pw');
      $password_envelope = new PhutilOpaqueEnvelope($pass);
      $confirm_envelope = new PhutilOpaqueEnvelope($conf);

      try {
        $engine->checkNewPassword($password_envelope, $confirm_envelope);
        $e_new = null;
        $e_conf = null;
      } catch (PhabricatorAuthPasswordException $ex) {
        $errors[] = $ex->getMessage();
        $e_new = $ex->getPasswordError();
        $e_conf = $ex->getConfirmError();
      }

      if (!$errors) {
        $password_object
          ->setPassword($password_envelope, $user)
          ->save();

        $next = $this->getPanelURI('?saved=true');

        id(new PhabricatorAuthSessionEngine())->terminateLoginSessions(
          $user,
          new PhutilOpaqueEnvelope(
            $request->getCookie(PhabricatorCookies::COOKIE_SESSION)));

        return id(new AphrontRedirectResponse())->setURI($next);
      }
    }

    if ($password_object->getID()) {
      try {
        $can_upgrade = $password_object->canUpgrade();
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
      ->setViewer($viewer)
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
        $password_object->newPasswordEnvelope()));

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
      ->setFormErrors($errors)
      ->setBackground(PHUIObjectBoxView::WHITE_CONFIG)
      ->setForm($form);

    return array(
      $form_box,
      $algo_box,
      $info_view,
    );
  }

  private function newSetPasswordView(AphrontRequest $request) {
    $viewer = $request->getUser();
    $user = $this->getUser();

    $form = id(new AphrontFormView())
      ->setViewer($viewer)
      ->appendRemarkupInstructions(
        pht(
          'Your account does not currently have a password set. You can '.
          'choose a password by performing a password reset.'))
      ->appendControl(
        id(new AphrontFormSubmitControl())
          ->addCancelButton('/login/email/', pht('Reset Password')));

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Set Password'))
      ->setBackground(PHUIObjectBoxView::WHITE_CONFIG)
      ->setForm($form);

    return $form_box;
  }


}
