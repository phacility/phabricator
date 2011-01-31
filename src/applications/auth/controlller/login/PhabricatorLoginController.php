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
    $login_name = $request->getCookie('phusr');
    if ($request->isFormPost()) {
      $login_name = $request->getStr('login');

      $user = id(new PhabricatorUser())->loadOneWhere(
        'username = %s',
        $login_name);

      $user->setPassword('asdf');
      $user->save();

      $okay = false;
      if ($user) {
        if ($user->comparePassword($request->getStr('password'))) {
          $conn_w = $user->establishConnection('w');

          $urandom = fopen('/dev/urandom', 'r');
          if (!$urandom) {
            throw new Exception("Failed to open /dev/urandom!");
          }
          $entropy = fread($urandom, 20);
          if (strlen($entropy) != 20) {
            throw new Exception("Failed to read /dev/urandom!");
          }

          $session_key = sha1($entropy);
          queryfx(
            $conn_w,
            'INSERT INTO phabricator_session '.
              '(userPHID, type, sessionKey, sessionStart)'.
            ' VALUES '.
              '(%s, %s, %s, UNIX_TIMESTAMP()) '.
            'ON DUPLICATE KEY UPDATE '.
              'sessionKey = VALUES(sessionKey), '.
              'sessionStart = VALUES(sessionStart)',
            $user->getPHID(),
            'web',
            $session_key);

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
          ->setLabel('Login')
          ->setName('login')
          ->setValue($login_name))
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
