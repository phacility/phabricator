<?php

final class PhabricatorAuthSetPasswordController
  extends PhabricatorAuthController {

  public function shouldAllowPartialSessions() {
    return true;
  }

  public function shouldAllowLegallyNonCompliantUsers() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    if (!PhabricatorPasswordAuthProvider::getPasswordProvider()) {
      return new Aphront404Response();
    }

    $token = id(new PhabricatorAuthSessionEngine())->requireHighSecuritySession(
      $viewer,
      $request,
      '/');

    $key = $request->getStr('key');
    $password_type = PhabricatorAuthPasswordResetTemporaryTokenType::TOKENTYPE;
    if (!$key) {
      return new Aphront404Response();
    }

    $auth_token = id(new PhabricatorAuthTemporaryTokenQuery())
      ->setViewer($viewer)
      ->withTokenResources(array($viewer->getPHID()))
      ->withTokenTypes(array($password_type))
      ->withTokenCodes(array(PhabricatorHash::weakDigest($key)))
      ->withExpired(false)
      ->executeOne();
    if (!$auth_token) {
      return new Aphront404Response();
    }

    $min_len = PhabricatorEnv::getEnvConfig('account.minimum-password-length');
    $min_len = (int)$min_len;

    $e_password = true;
    $e_confirm = true;
    $errors = array();
    if ($request->isFormPost()) {
      $password = $request->getStr('password');
      $confirm = $request->getStr('confirm');

      $e_password = null;
      $e_confirm = null;

      if (!strlen($password)) {
        $errors[] = pht('You must choose a password or skip this step.');
        $e_password = pht('Required');
      } else if (strlen($password) < $min_len) {
        $errors[] = pht(
          'The selected password is too short. Passwords must be a minimum '.
          'of %s characters.',
          new PhutilNumber($min_len));
        $e_password = pht('Too Short');
      } else if (!strlen($confirm)) {
        $errors[] = pht('You must confirm the selecetd password.');
        $e_confirm = pht('Required');
      } else if ($password !== $confirm) {
        $errors[] = pht('The password and confirmation do not match.');
        $e_password = pht('Invalid');
        $e_confirm = pht('Invalid');
      } else if (PhabricatorCommonPasswords::isCommonPassword($password)) {
        $e_password = pht('Very Weak');
        $errors[] = pht(
          'The selected password is very weak: it is one of the most common '.
          'passwords in use. Choose a stronger password.');
      }

      if (!$errors) {
        $envelope = new PhutilOpaqueEnvelope($password);

        // This write is unguarded because the CSRF token has already
        // been checked in the call to $request->isFormPost() and
        // the CSRF token depends on the password hash, so when it
        // is changed here the CSRF token check will fail.
        $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();

          id(new PhabricatorUserEditor())
            ->setActor($viewer)
            ->changePassword($viewer, $envelope);

        unset($unguarded);

        // Destroy the token.
        $auth_token->delete();

        return id(new AphrontRedirectResponse())->setURI('/');
      }
    }

    $len_caption = null;
    if ($min_len) {
      $len_caption = pht('Minimum password length: %d characters.', $min_len);
    }

    if ($viewer->hasPassword()) {
      $title = pht('Reset Password');
      $crumb = pht('Reset Password');
      $submit = pht('Reset Password');
    } else {
      $title = pht('Set Password');
      $crumb = pht('Set Password');
      $submit = pht('Set Account Password');
    }

    $form = id(new AphrontFormView())
      ->setViewer($viewer)
      ->addHiddenInput('key', $key)
      ->appendChild(
        id(new AphrontFormPasswordControl())
          ->setDisableAutocomplete(true)
          ->setLabel(pht('New Password'))
          ->setError($e_password)
          ->setName('password'))
      ->appendChild(
        id(new AphrontFormPasswordControl())
          ->setDisableAutocomplete(true)
          ->setLabel(pht('Confirm Password'))
          ->setCaption($len_caption)
          ->setError($e_confirm)
          ->setName('confirm'))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->addCancelButton('/', pht('Skip This Step'))
          ->setValue($submit));

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText($title)
      ->setFormErrors($errors)
      ->setBackground(PHUIObjectBoxView::WHITE_CONFIG)
      ->setForm($form);

    $main_view = id(new PHUITwoColumnView())
      ->setFooter($form_box);

    $crumbs = $this->buildApplicationCrumbs()
      ->addTextCrumb($crumb)
      ->setBorder(true);

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($main_view);
  }
}
