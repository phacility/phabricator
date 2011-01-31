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

class PhabricatorLoginController extends PhabricatorAuthController {

  public function shouldRequireLogin() {
    return false;
  }

  public function processRequest() {
    $request = $this->getRequest();

    $error = false;
    $username = $request->getCookie('phusr');
    if ($request->isFormPost()) {
      $username = $request->getStr('username');

      $user = id(new PhabricatorUser())->loadOneWhere(
        'username = %s',
        $username);

      $user->setPassword('asdf');
      $user->save();

      $okay = false;
      if ($user) {
        if ($user->comparePassword($request->getStr('password'))) {

          $session_key = $user->establishSession('web');

          $request->setCookie('phusr', $user->getUsername());
          $request->setCookie('phsid', $session_key);

          return id(new AphrontRedirectResponse())
            ->setURI('/');
        }
      }

      if (!$okay) {
        $request->clearCookie('phusr');
        $request->clearCookie('phsid');
      }

      $error = true;
    }

    $error_view = null;
    if ($error) {
      $error_view = new AphrontErrorView();
      $error_view->setTitle('Bad username/password.');
    }

    $form = new AphrontFormView();
    $form
      ->setUser($request->getUser())
      ->setAction('/login/')
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Username')
          ->setName('username')
          ->setValue($username))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Password')
          ->setName('password'))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue('Login'));


    $panel = new AphrontPanelView();
    $panel->setHeader('Phabricator Login');
    $panel->setWidth(AphrontPanelView::WIDTH_FORM);
    $panel->appendChild($form);


    // TODO: Hardcoded junk
    $connect_uri = "https://www.facebook.com/dialog/oauth";

    $user = $request->getUser();

    $facebook_connect = new AphrontFormView();
    $facebook_connect
      ->setAction($connect_uri)
      ->addHiddenInput('client_id', 184510521580034)
      ->addHiddenInput('redirect_uri', 'http://local.aphront.com/facebook-connect/')
      ->addHiddenInput('scope', 'email')
      ->addHiddenInput('state', $user->getCSRFToken())
      ->setUser($request->getUser())
      ->setMethod('GET')
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue("Login with Facebook Connect \xC2\xBB"));

    $panel->appendChild('<br /><h1>Login with Facebook</h1>');
    $panel->appendChild($facebook_connect);

    return $this->buildStandardPageResponse(
      array(
        $error_view,
        $panel,
      ),
      array(
        'title' => 'Login',
      ));
  }

}
