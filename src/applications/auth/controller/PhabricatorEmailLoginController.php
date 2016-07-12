<?php

final class PhabricatorEmailLoginController
  extends PhabricatorAuthController {

  public function shouldRequireLogin() {
    return false;
  }

  public function handleRequest(AphrontRequest $request) {

    if (!PhabricatorPasswordAuthProvider::getPasswordProvider()) {
      return new Aphront400Response();
    }

    $e_email = true;
    $e_captcha = true;
    $errors = array();

    $is_serious = PhabricatorEnv::getEnvConfig('phabricator.serious-business');

    if ($request->isFormPost()) {
      $e_email = null;
      $e_captcha = pht('Again');

      $captcha_ok = AphrontFormRecaptchaControl::processCaptcha($request);
      if (!$captcha_ok) {
        $errors[] = pht('Captcha response is incorrect, try again.');
        $e_captcha = pht('Invalid');
      }

      $email = $request->getStr('email');
      if (!strlen($email)) {
       $errors[] = pht('You must provide an email address.');
       $e_email = pht('Required');
      }

      if (!$errors) {
        // NOTE: Don't validate the email unless the captcha is good; this makes
        // it expensive to fish for valid email addresses while giving the user
        // a better error if they goof their email.

        $target_email = id(new PhabricatorUserEmail())->loadOneWhere(
          'address = %s',
          $email);

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
              'That email address is not verified. You can only send '.
              'password reset links to a verified address.');
            $e_email = pht('Unverified');
          }
        }

        if (!$errors) {
          $engine = new PhabricatorAuthSessionEngine();
          $uri = $engine->getOneTimeLoginURI(
            $target_user,
            null,
            PhabricatorAuthSessionEngine::ONETIME_RESET);

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

          $mail = id(new PhabricatorMetaMTAMail())
            ->setSubject(pht('[Phabricator] Password Reset'))
            ->setForceDelivery(true)
            ->addRawTos(array($target_email->getAddress()))
            ->setBody($body)
            ->saveAndSend();

          return $this->newDialog()
            ->setTitle(pht('Check Your Email'))
            ->setShortTitle(pht('Email Sent'))
            ->appendParagraph(
              pht('An email has been sent with a link you can use to login.'))
            ->addCancelButton('/', pht('Done'));
        }
      }
    }

    $error_view = null;
    if ($errors) {
      $error_view = new PHUIInfoView();
      $error_view->setErrors($errors);
    }

    $email_auth = new PHUIFormLayoutView();
    $email_auth->appendChild($error_view);
    $email_auth
      ->setUser($request->getUser())
      ->setFullWidth(true)
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Email'))
          ->setName('email')
          ->setValue($request->getStr('email'))
          ->setError($e_email))
      ->appendChild(
        id(new AphrontFormRecaptchaControl())
          ->setLabel(pht('Captcha'))
          ->setError($e_captcha));

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Reset Password'));
    $crumbs->setBorder(true);

    $dialog = new AphrontDialogView();
    $dialog->setUser($request->getUser());
    $dialog->setTitle(pht('Forgot Password / Email Login'));
    $dialog->appendChild($email_auth);
    $dialog->addSubmitButton(pht('Send Email'));
    $dialog->setSubmitURI('/login/email/');

    return $this->newPage()
      ->setTitle(pht('Forgot Password'))
      ->setCrumbs($crumbs)
      ->appendChild($dialog);

  }

}
