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

class PhabricatorResetPasswordController extends PhabricatorAuthController {

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    if (!PhabricatorEnv::getEnvConfig('auth.password-auth-enabled')) {
      return new Aphront400Response();
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

        // The CSRF token depends on the user's password hash. When we change
        // it, we cause the CSRF check to fail. We don't need to do a CSRF
        // check here because we've already performed one in the isFormPost()
        // call earlier.

        $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
          $user->setPassword($pass);
          $user->save();
        unset($unguarded);

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
      ->setUser($user)
      ->setAction('/login/reset/')
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
        'title' => 'Reset Password',
      ));
  }

}
