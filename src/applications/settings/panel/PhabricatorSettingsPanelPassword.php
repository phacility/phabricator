<?php

final class PhabricatorSettingsPanelPassword
  extends PhabricatorSettingsPanel {

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
    // There's no sense in showing a change password panel if the user
    // can't change their password...

    if (!PhabricatorEnv::getEnvConfig('account.editable')) {
      return false;
    }

    // ...or this install doesn't support password authentication at all.

    if (!PhabricatorEnv::getEnvConfig('auth.password-auth-enabled')) {
      return false;
    }

    return true;
  }

  public function processRequest(AphrontRequest $request) {
    $user = $request->getUser();

    $min_len = PhabricatorEnv::getEnvConfig('account.minimum-password-length');
    $min_len = (int)$min_len;

    // NOTE: To change your password, you need to prove you own the account,
    // either by providing the old password or by carrying a token to
    // the workflow from a password reset email.

    $token = $request->getStr('token');

    $valid_token = false;
    if ($token) {
      $email_address = $request->getStr('email');
      $email = id(new PhabricatorUserEmail())->loadOneWhere(
        'address = %s',
        $email_address);
      if ($email) {
        $valid_token = $user->validateEmailToken($email, $token);
      }
    }

    $e_old = true;
    $e_new = true;
    $e_conf = true;

    $errors = array();
    if ($request->isFormPost()) {
      if (!$valid_token) {
        $envelope = new PhutilOpaqueEnvelope($request->getStr('old_pw'));
        if (!$user->comparePassword($envelope)) {
          $errors[] = 'The old password you entered is incorrect.';
          $e_old = 'Invalid';
        }
      }

      $pass = $request->getStr('new_pw');
      $conf = $request->getStr('conf_pw');

      if (strlen($pass) < $min_len) {
        $errors[] = 'Your new password is too short.';
        $e_new = 'Too Short';
      }

      if ($pass !== $conf) {
        $errors[] = 'New password and confirmation do not match.';
        $e_conf = 'Invalid';
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

        if ($valid_token) {
          // If this is a password set/reset, kick the user to the home page
          // after we update their account.
          $next = '/';
        } else {
          $next = $this->getPanelURI('?saved=true');
        }

        return id(new AphrontRedirectResponse())->setURI($next);
      }
    }

    $notice = null;
    if (!$errors) {
      if ($request->getStr('saved')) {
        $notice = new AphrontErrorView();
        $notice->setSeverity(AphrontErrorView::SEVERITY_NOTICE);
        $notice->setTitle('Changes Saved');
        $notice->appendChild('<p>Your password has been updated.</p>');
      }
    } else {
      $notice = new AphrontErrorView();
      $notice->setTitle('Error Changing Password');
      $notice->setErrors($errors);
    }

    $len_caption = null;
    if ($min_len) {
      $len_caption = 'Minimum password length: '.$min_len.' characters.';
    }

    $form = new AphrontFormView();
    $form
      ->setUser($user)
      ->addHiddenInput('token', $token);

    if (!$valid_token) {
      $form->appendChild(
        id(new AphrontFormPasswordControl())
          ->setLabel('Old Password')
          ->setError($e_old)
          ->setName('old_pw'));
    }

    $form
      ->appendChild(
        id(new AphrontFormPasswordControl())
          ->setLabel('New Password')
          ->setError($e_new)
          ->setName('new_pw'));
    $form
      ->appendChild(
        id(new AphrontFormPasswordControl())
          ->setLabel('Confirm Password')
          ->setCaption($len_caption)
          ->setError($e_conf)
          ->setName('conf_pw'));
    $form
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue('Save'));

    $panel = new AphrontPanelView();
    $panel->setHeader('Change Password');
    $panel->setWidth(AphrontPanelView::WIDTH_FORM);
    $panel->appendChild($form);

    return array(
      $notice,
      $panel,
    );
  }
}
