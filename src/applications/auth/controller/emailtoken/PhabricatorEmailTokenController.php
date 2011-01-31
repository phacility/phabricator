<?php

/*
 * Copyright 2011 Facebook, Inc.
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

class PhabricatorEmailTokenController extends PhabricatorAuthController {

  private $token;

  public function shouldRequireLogin() {
    return false;
  }

  public function willProcessRequest(array $data) {
    $this->token = $data['token'];
  }

  public function processRequest() {
    $request = $this->getRequest();

    $token = $this->token;
    $email = $request->getStr('email');

    $target_user = id(new PhabricatorUser())->loadOneWhere(
      'email = %s',
      $email);

    if (!$target_user || !$target_user->validateEmailToken($token)) {
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
          'title' => 'Email Sent',
        ));
    }

    if ($request->getUser()->getPHID() != $target_user->getPHID()) {
      $session_key = $target_user->establishSession('web');
      $request->setCookie('phusr', $target_user->getUsername());
      $request->setCookie('phsid', $session_key);
    }

    $errors = array();

    $e_pass = true;
    $e_confirm = true;

    if ($request->isFormPost()) {
      $e_pass = 'Error';
      $e_confirm = 'Error';

      $pass = $request->getStr('password');
      $confirm = $request->getStr('confirm');

      if (strlen($pass) < 3) {
        $errors[] = 'That password is ridiculously short.';
      }

      if ($pass !== $confirm) {
        $errors[] = "Passwords do not match.";
      }

      if (!$errors) {
        $target_user->setPassword($pass);
        $target_user->save();
        return id(new AphrontRedirectResponse())
          ->setURI('/');
      }
    }

    if ($errors) {
      $error_view = new AphrontErrorView();
      $error_view->setTitle('Password Reset Failed');
      $error_view->setErrors($errors);
    } else {
      $error_view = null;
    }

    $form = new AphrontFormView();
    $form
      ->setUser($target_user)
      ->setAction('/login/etoken/'.$token.'/')
      ->addHiddenInput('email', $email)
      ->appendChild(
        id(new AphrontFormPasswordControl())
          ->setLabel('New Password')
          ->setName('password')
          ->setError($e_pass))
      ->appendChild(
        id(new AphrontFormPasswordControl())
          ->setLabel('Confirm Password')
          ->setName('confirm')
          ->setError($e_confirm))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue('Reset Password')
          ->addCancelButton('/', 'Skip'));

    $panel = new AphrontPanelView();
    $panel->setWidth(AphrontPanelView::WIDTH_FORM);
    $panel->setHeader('Reset Password');
    $panel->appendChild($form);

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
