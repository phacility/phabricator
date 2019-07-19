<?php

final class PhabricatorEmailLoginController
  extends PhabricatorAuthController {

  public function shouldRequireLogin() {
    return false;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $is_logged_in = $viewer->isLoggedIn();

    $e_email = true;
    $e_captcha = true;
    $errors = array();

    if ($is_logged_in) {
      if (!$this->isPasswordAuthEnabled()) {
        return $this->newDialog()
          ->setTitle(pht('No Password Auth'))
          ->appendParagraph(
            pht(
              'Password authentication is not enabled and you are already '.
              'logged in. There is nothing for you here.'))
          ->addCancelButton('/', pht('Continue'));
      }

      $v_email = $viewer->loadPrimaryEmailAddress();
    } else {
      $v_email = $request->getStr('email');
    }

    if ($request->isFormPost()) {
      $e_email = null;
      $e_captcha = pht('Again');

      if (!$is_logged_in) {
        $captcha_ok = AphrontFormRecaptchaControl::processCaptcha($request);
        if (!$captcha_ok) {
          $errors[] = pht('Captcha response is incorrect, try again.');
          $e_captcha = pht('Invalid');
        }
      }

      if (!strlen($v_email)) {
       $errors[] = pht('You must provide an email address.');
       $e_email = pht('Required');
      }

      if (!$errors) {
        // NOTE: Don't validate the email unless the captcha is good; this makes
        // it expensive to fish for valid email addresses while giving the user
        // a better error if they goof their email.

        $action_actor = PhabricatorSystemActionEngine::newActorFromRequest(
          $request);

        PhabricatorSystemActionEngine::willTakeAction(
          array($action_actor),
          new PhabricatorAuthTryEmailLoginAction(),
          1);

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
          $target_address = new PhutilEmailAddress($target_email->getAddress());

          $user_log = PhabricatorUserLog::initializeNewLog(
            $viewer,
            $target_user->getPHID(),
            PhabricatorEmailLoginUserLogType::LOGTYPE);

          $mail_engine = id(new PhabricatorPeopleEmailLoginMailEngine())
            ->setSender($viewer)
            ->setRecipient($target_user)
            ->setRecipientAddress($target_address)
            ->setActivityLog($user_log);

          try {
            $mail_engine->validateMail();
          } catch (PhabricatorPeopleMailEngineException $ex) {
            return $this->newDialog()
              ->setTitle($ex->getTitle())
              ->appendParagraph($ex->getBody())
              ->addCancelButton('/auth/start/', pht('Done'));
          }

          $mail_engine->sendMail();

          if ($is_logged_in) {
            $instructions = pht(
              'An email has been sent containing a link you can use to set '.
              'a password for your account.');
          } else {
            $instructions = pht(
              'An email has been sent containing a link you can use to log '.
              'in to your account.');
          }

          return $this->newDialog()
            ->setTitle(pht('Check Your Email'))
            ->setShortTitle(pht('Email Sent'))
            ->appendParagraph($instructions)
            ->addCancelButton('/', pht('Done'));
        }
      }
    }

    $form = id(new AphrontFormView())
      ->setViewer($viewer);

    if ($this->isPasswordAuthEnabled()) {
      if ($is_logged_in) {
        $title = pht('Set Password');
        $form->appendRemarkupInstructions(
          pht(
            'A password reset link will be sent to your primary email '.
            'address. Follow the link to set an account password.'));
      } else {
        $title = pht('Password Reset');
        $form->appendRemarkupInstructions(
          pht(
            'To reset your password, provide your email address. An email '.
            'with a login link will be sent to you.'));
      }
    } else {
      $title = pht('Email Login');
      $form->appendRemarkupInstructions(
        pht(
          'To access your account, provide your email address. An email '.
          'with a login link will be sent to you.'));
    }

    if ($is_logged_in) {
      $address_control = new AphrontFormStaticControl();
    } else {
      $address_control = id(new AphrontFormTextControl())
        ->setName('email')
        ->setError($e_email);
    }

    $address_control
      ->setLabel(pht('Email Address'))
      ->setValue($v_email);

    $form
      ->appendControl($address_control);

    if (!$is_logged_in) {
      $form->appendControl(
        id(new AphrontFormRecaptchaControl())
          ->setLabel(pht('Captcha'))
          ->setError($e_captcha));
    }

    return $this->newDialog()
      ->setTitle($title)
      ->setErrors($errors)
      ->setWidth(AphrontDialogView::WIDTH_FORM)
      ->appendForm($form)
      ->addCancelButton('/auth/start/')
      ->addSubmitButton(pht('Send Email'));
  }

  private function isPasswordAuthEnabled() {
    return (bool)PhabricatorPasswordAuthProvider::getPasswordProvider();
  }
}
