<?php

final class PhabricatorEmailLoginController
  extends PhabricatorAuthController {

  public function shouldRequireLogin() {
    return false;
  }

  public function processRequest() {
    $request = $this->getRequest();

    if (!PhabricatorEnv::getEnvConfig('auth.password-auth-enabled')) {
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
        $errors[] = pht("Captcha response is incorrect, try again.");
        $e_captcha = pht('Invalid');
      }

      $email = $request->getStr('email');
      if (!strlen($email)) {
       $errors[] = pht("You must provide an email address.");
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
            pht("There is no account associated with that email address.");
          $e_email = pht("Invalid");
        }

        if (!$errors) {
          $uri = $target_user->getEmailLoginURI($target_email);
          if ($is_serious) {
            $body = <<<EOBODY
You can use this link to reset your Phabricator password:

  {$uri}

EOBODY;
          } else {
            $body = <<<EOBODY
Condolences on forgetting your password. You can use this link to reset it:

  {$uri}

After you set a new password, consider writing it down on a sticky note and
attaching it to your monitor so you don't forget again! Choosing a very short,
easy-to-remember password like "cat" or "1234" might also help.

Best Wishes,
Phabricator

EOBODY;
          }

          // NOTE: Don't set the user as 'from', or they may not receive the
          // mail if they have the "don't send me email about my own actions"
          // preference set.

          $mail = new PhabricatorMetaMTAMail();
          $mail->setSubject('[Phabricator] Password Reset');
          $mail->addTos(
            array(
              $target_user->getPHID(),
            ));
          $mail->setBody($body);
          $mail->saveAndSend();

          $view = new AphrontRequestFailureView();
          $view->setHeader(pht('Check Your Email'));
          $view->appendChild(phutil_tag('p', array(), pht(
              'An email has been sent with a link you can use to login.')));
          return $this->buildStandardPageResponse(
            $view,
            array(
              'title' => pht('Email Sent'),
            ));
        }
      }

    }

    $email_auth = new AphrontFormView();
    $email_auth
      ->setAction('/login/email/')
      ->setUser($request->getUser())
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Email'))
          ->setName('email')
          ->setValue($request->getStr('email'))
          ->setError($e_email))
      ->appendChild(
        id(new AphrontFormRecaptchaControl())
          ->setLabel(pht('Captcha'))
          ->setError($e_captcha))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Send Email')));

    $error_view = null;
    if ($errors) {
      $error_view = new AphrontErrorView();
      $error_view->setTitle(pht('Login Error'));
      $error_view->setErrors($errors);
    }


    $panel = new AphrontPanelView();
    $panel->setWidth(AphrontPanelView::WIDTH_FORM);
    $panel->appendChild(phutil_tag('h1', array(), pht(
      'Forgot Password / Email Login')));
    $panel->appendChild($email_auth);
    $panel->setNoBackground();

    return $this->buildApplicationPage(
      array(
        $error_view,
        $panel,
      ),
      array(
        'title' => pht('Forgot Password'),
        'device' => true,
      ));
  }

}
