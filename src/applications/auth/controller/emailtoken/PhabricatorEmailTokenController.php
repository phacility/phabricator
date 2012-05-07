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

final class PhabricatorEmailTokenController
  extends PhabricatorAuthController {

  private $token;

  public function shouldRequireLogin() {
    return false;
  }

  public function willProcessRequest(array $data) {
    $this->token = $data['token'];
  }

  public function processRequest() {
    $request = $this->getRequest();

    if (!PhabricatorEnv::getEnvConfig('auth.password-auth-enabled')) {
      return new Aphront400Response();
    }

    if ($request->getUser()->getPHID()) {
      $view = new AphrontRequestFailureView();
      $view->setHeader('Already Logged In');
      $view->appendChild(
        '<p>You are already logged in.</p>');
      $view->appendChild(
        '<div class="aphront-failure-continue">'.
          '<a class="button" href="/">Return Home</a>'.
        '</div>');
      return $this->buildStandardPageResponse(
        $view,
        array(
          'title' => 'Already Logged In',
        ));
    }

    $token = $this->token;
    $email = $request->getStr('email');

    // NOTE: We need to bind verification to **addresses**, not **users**,
    // because we verify addresses when they're used to login this way, and if
    // we have a user-based verification you can:
    //
    //  - Add some address you do not own;
    //  - request a password reset;
    //  - change the URI in the email to the address you don't own;
    //  - login via the email link; and
    //  - get a "verified" address you don't control.

    $target_email = id(new PhabricatorUserEmail())->loadOneWhere(
      'address = %s',
      $email);

    $target_user = null;
    if ($target_email) {
      $target_user = id(new PhabricatorUser())->loadOneWhere(
        'phid = %s',
        $target_email->getUserPHID());
    }

    if (!$target_email ||
        !$target_user  ||
        !$target_user->validateEmailToken($target_email, $token)) {

      $view = new AphrontRequestFailureView();
      $view->setHeader('Unable to Login');
      $view->appendChild(
        '<p>The authentication information in the link you clicked is '.
        'invalid or out of date. Make sure you are copy-and-pasting the '.
        'entire link into your browser. You can try again, or request '.
        'a new email.</p>');
      $view->appendChild(
        '<div class="aphront-failure-continue">'.
          '<a class="button" href="/login/email/">Send Another Email</a>'.
        '</div>');

      return $this->buildStandardPageResponse(
        $view,
        array(
          'title' => 'Login Failure',
        ));
    }

    // Verify email so that clicking the link in the "Welcome" email is good
    // enough, without requiring users to go through a second round of email
    // verification.

    $target_email->setIsVerified(1);
    $target_email->save();

    $session_key = $target_user->establishSession('web');
    $request->setCookie('phusr', $target_user->getUsername());
    $request->setCookie('phsid', $session_key);

    if (PhabricatorEnv::getEnvConfig('account.editable')) {
      $next = (string)id(new PhutilURI('/settings/page/password/'))
        ->setQueryParams(
          array(
            'token' => $token,
            'email' => $email,
          ));
    } else {
      $next = '/';
    }

    $uri = new PhutilURI('/login/validate/');
    $uri->setQueryParams(
      array(
        'phusr' => $target_user->getUsername(),
        'next'  => $next,
      ));

    return id(new AphrontRedirectResponse())
      ->setURI((string)$uri);
  }
}
