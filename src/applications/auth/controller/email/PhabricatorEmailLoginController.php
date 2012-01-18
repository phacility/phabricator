<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

class PhabricatorEmailLoginController extends PhabricatorAuthController {

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
      $e_captcha = 'Again';

      $captcha_ok = AphrontFormRecaptchaControl::processCaptcha($request);
      if (!$captcha_ok) {
        $errors[] = "Captcha response is incorrect, try again.";
        $e_captcha = 'Invalid';
      }

      $email = $request->getStr('email');
      if (!strlen($email)) {
       $errors[] = "You must provide an email address.";
       $e_email = 'Required';
      }

      if (!$errors) {
        // NOTE: Don't validate the email unless the captcha is good; this makes
        // it expensive to fish for valid email addresses while giving the user
        // a better error if they goof their email.

        $target_user = id(new PhabricatorUser())->loadOneWhere(
          'email = %s',
          $email);

        if (!$target_user) {
          $errors[] = "There is no account associated with that email address.";
          $e_email = "Invalid";
        }

        if (!$errors) {
          $uri = $target_user->getEmailLoginURI();
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
          $view->setHeader('Check Your Email');
          $view->appendChild(
            '<p>An email has been sent with a link you can use to login.</p>');
          return $this->buildStandardPageResponse(
            $view,
            array(
              'title' => 'Email Sent',
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
          ->setLabel('Email')
          ->setName('email')
          ->setValue($request->getStr('email'))
          ->setError($e_email))
      ->appendChild(
        id(new AphrontFormRecaptchaControl())
          ->setLabel('Captcha')
          ->setError($e_captcha))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue('Send Email'));

    $error_view = null;
    if ($errors) {
      $error_view = new AphrontErrorView();
      $error_view->setTitle('Login Error');
      $error_view->setErrors($errors);
    }


    $panel = new AphrontPanelView();
    $panel->setWidth(AphrontPanelView::WIDTH_FORM);
    $panel->appendChild('<h1>Forgot Password / Email Login</h1>');
    $panel->appendChild($email_auth);

    return $this->buildStandardPageResponse(
      array(
        $error_view,
        $panel,
      ),
      array(
        'title' => 'Create New Account',
      ));
  }

}
