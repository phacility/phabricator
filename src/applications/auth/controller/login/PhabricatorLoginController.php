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

    if ($request->getUser()->getPHID()) {
      // Kick the user out if they're already logged in.
      return id(new AphrontRedirectResponse())->setURI('/');
    }

    $next_uri = $this->getRequest()->getPath();
    $request->setCookie('next_uri', $next_uri);
    if ($next_uri == '/login/' && !$request->isFormPost()) {
      // The user went straight to /login/, so presumably they want to go
      // to the dashboard upon logging in. Because, you know, that's logical.
      // And people are logical. Sometimes... Fine, no they're not.
      // We check for POST here because getPath() would get reset to /login/.
       $request->setCookie('next_uri', '/');
    }

    // Always use $request->getCookie('next_uri', '/') after the above.

    $password_auth = PhabricatorEnv::getEnvConfig('auth.password-auth-enabled');

    $forms = array();

    $error_view = null;
    if ($password_auth) {
      $error = false;
      $username_or_email = $request->getCookie('phusr');
      if ($request->isFormPost()) {
        $username_or_email = $request->getStr('username_or_email');

        $user = id(new PhabricatorUser())->loadOneWhere(
          'username = %s',
          $username_or_email);

        if (!$user) {
          $user = id(new PhabricatorUser())->loadOneWhere(
            'email = %s',
            $username_or_email);
        }

        $okay = false;
        if ($user) {
          if ($user->comparePassword($request->getStr('password'))) {

            $session_key = $user->establishSession('web');

            $request->setCookie('phusr', $user->getUsername());
            $request->setCookie('phsid', $session_key);

            return id(new AphrontRedirectResponse())
              ->setURI($request->getCookie('next_uri', '/'));
          } else {
            $log = PhabricatorUserLog::newLog(
              null,
              $user,
              PhabricatorUserLog::ACTION_LOGIN_FAILURE);
            $log->save();
          }
        }

        if (!$okay) {
          $request->clearCookie('phusr');
          $request->clearCookie('phsid');
        }

        $error = true;
      }

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
            ->setLabel('Username/Email')
            ->setName('username_or_email')
            ->setValue($username_or_email))
        ->appendChild(
          id(new AphrontFormPasswordControl())
            ->setLabel('Password')
            ->setName('password')
            ->setCaption(
              '<a href="/login/email/">'.
                'Forgot your password? / Email Login</a>'))
        ->appendChild(
          id(new AphrontFormSubmitControl())
            ->setValue('Login'));


  //    $panel->setCreateButton('Register New Account', '/login/register/');
      $forms['Phabricator Login'] = $form;
    }

    $providers = array(
      PhabricatorOAuthProvider::PROVIDER_FACEBOOK,
      PhabricatorOAuthProvider::PROVIDER_GITHUB,
    );
    foreach ($providers as $provider_key) {
      $provider = PhabricatorOAuthProvider::newProvider($provider_key);

      $enabled = $provider->isProviderEnabled();
      if (!$enabled) {
        continue;
      }

      $auth_uri       = $provider->getAuthURI();
      $redirect_uri   = $provider->getRedirectURI();
      $client_id      = $provider->getClientID();
      $provider_name  = $provider->getProviderName();
      $minimum_scope  = $provider->getMinimumScope();

      // TODO: In theory we should use 'state' to prevent CSRF, but the total
      // effect of the CSRF attack is that an attacker can cause a user to login
      // to Phabricator if they're already logged into some OAuth provider. This
      // does not seem like the most severe threat in the world, and generating
      // CSRF for logged-out users is vaugely tricky.

      if ($provider->isProviderRegistrationEnabled()) {
        $title = "Login or Register with {$provider_name}";
        $body = "Login or register for Phabricator using your ".
                "{$provider_name} account.";
        $button = "Login or Register with {$provider_name}";
      } else {
        $title = "Login with {$provider_name}";
        $body = "Login to your existing Phabricator account using your ".
                "{$provider_name} account.<br /><br /><strong>You can not use ".
                "{$provider_name} to register a new account.</strong>";
        $button = "Login with {$provider_name}";
      }

      $auth_form = new AphrontFormView();
      $auth_form
        ->setAction($auth_uri)
        ->addHiddenInput('client_id', $client_id)
        ->addHiddenInput('redirect_uri', $redirect_uri)
        ->addHiddenInput('scope', $minimum_scope)
        ->setUser($request->getUser())
        ->setMethod('GET')
        ->appendChild(
          '<p class="aphront-form-instructions">'.$body.'</p>')
        ->appendChild(
          id(new AphrontFormSubmitControl())
            ->setValue("{$button} \xC2\xBB"));

      $forms[$title] = $auth_form;
    }

    $panel = new AphrontPanelView();
    $panel->setWidth(AphrontPanelView::WIDTH_FORM);
    foreach ($forms as $name => $form) {
      $panel->appendChild('<h1>'.$name.'</h1>');
      $panel->appendChild($form);
      $panel->appendChild('<br />');
    }

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
