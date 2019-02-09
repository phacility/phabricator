<?php

final class PhabricatorEmailLoginController
  extends PhabricatorAuthController {

  public function shouldRequireLogin() {
    return false;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $e_email = true;
    $e_captcha = true;
    $errors = array();

    $v_email = $request->getStr('email');
    if ($request->isFormPost()) {
      $e_email = null;
      $e_captcha = pht('Again');

      $captcha_ok = AphrontFormRecaptchaControl::processCaptcha($request);
      if (!$captcha_ok) {
        $errors[] = pht('Captcha response is incorrect, try again.');
        $e_captcha = pht('Invalid');
      }

      if (!strlen($v_email)) {
       $errors[] = pht('You must provide an email address.');
       $e_email = pht('Required');
      }

      if (!$errors) {
        // NOTE: Don't validate the email unless the captcha is good; this makes
        // it expensive to fish for valid email addresses while giving the user
        // a better error if they goof their email.

        $target_email = id(new PhabricatorUserEmail())->loadOneWhere(
          'address = %s',
          $v_email);

        $target_user = null;
        if ($target_email) {
          $target_user = id(new PhabricatorUser())->loadOneWhere(
            'phid = %s',
            $target_email->getUserPHID());
        }

        if (!$target_user) {
          $errors[] =
            pht('There is no account associated with that email address.');
          $e_email = pht('Invalid');
        }

        // If this address is unverified, only send a reset link to it if
        // the account has no verified addresses. This prevents an opportunistic
        // attacker from compromising an account if a user adds an email
        // address but mistypes it and doesn't notice.

        // (For a newly created account, all the addresses may be unverified,
        // which is why we'll send to an unverified address in that case.)

        if ($target_email && !$target_email->getIsVerified()) {
          $verified_addresses = id(new PhabricatorUserEmail())->loadAllWhere(
            'userPHID = %s AND isVerified = 1',
            $target_email->getUserPHID());
          if ($verified_addresses) {
            $errors[] = pht(
              'That email address is not verified, but the account it is '.
              'connected to has at least one other verified address. When an '.
              'account has at least one verified address, you can only send '.
              'password reset links to one of the verified addresses. Try '.
              'a verified address instead.');
            $e_email = pht('Unverified');
          }
        }

        if (!$errors) {
          $body = $this->newAccountLoginMailBody($target_user);

          $mail = id(new PhabricatorMetaMTAMail())
            ->setSubject(pht('[Phabricator] Account Login Link'))
            ->setForceDelivery(true)
            ->addRawTos(array($target_email->getAddress()))
            ->setBody($body)
            ->saveAndSend();

          return $this->newDialog()
            ->setTitle(pht('Check Your Email'))
            ->setShortTitle(pht('Email Sent'))
            ->appendParagraph(
              pht('An email has been sent with a link you can use to log in.'))
            ->addCancelButton('/', pht('Done'));
        }
      }
    }

    $form = id(new AphrontFormView())
      ->setViewer($viewer);

    if ($this->isPasswordAuthEnabled()) {
      $form->appendRemarkupInstructions(
        pht(
          'To reset your password, provide your email address. An email '.
          'with a login link will be sent to you.'));
    } else {
      $form->appendRemarkupInstructions(
        pht(
          'To access your account, provide your email address. An email '.
          'with a login link will be sent to you.'));
    }

    $form
      ->appendControl(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Email Address'))
          ->setName('email')
          ->setValue($v_email)
          ->setError($e_email))
      ->appendControl(
        id(new AphrontFormRecaptchaControl())
          ->setLabel(pht('Captcha'))
          ->setError($e_captcha));

    if ($this->isPasswordAuthEnabled()) {
      $title = pht('Password Reset');
    } else {
      $title = pht('Email Login');
    }

    return $this->newDialog()
      ->setTitle($title)
      ->setErrors($errors)
      ->setWidth(AphrontDialogView::WIDTH_FORM)
      ->appendForm($form)
      ->addCancelButton('/auth/start/')
      ->addSubmitButton(pht('Send Email'));
  }

  private function newAccountLoginMailBody(PhabricatorUser $user) {
    $engine = new PhabricatorAuthSessionEngine();
    $uri = $engine->getOneTimeLoginURI(
      $user,
      null,
      PhabricatorAuthSessionEngine::ONETIME_RESET);

    $is_serious = PhabricatorEnv::getEnvConfig('phabricator.serious-business');
    $have_passwords = $this->isPasswordAuthEnabled();

    if ($have_passwords) {
      if ($is_serious) {
        $body = pht(
          "You can use this link to reset your Phabricator password:".
          "\n\n  %s\n",
          $uri);
      } else {
        $body = pht(
          "Condolences on forgetting your password. You can use this ".
          "link to reset it:\n\n".
          "  %s\n\n".
          "After you set a new password, consider writing it down on a ".
          "sticky note and attaching it to your monitor so you don't ".
          "forget again! Choosing a very short, easy-to-remember password ".
          "like \"cat\" or \"1234\" might also help.\n\n".
          "Best Wishes,\nPhabricator\n",
          $uri);

      }
    } else {
      $body = pht(
        "You can use this login link to regain access to your Phabricator ".
        "account:".
        "\n\n".
        "  %s\n",
        $uri);
    }

    return $body;
  }

  private function isPasswordAuthEnabled() {
    return (bool)PhabricatorPasswordAuthProvider::getPasswordProvider();
  }
}
