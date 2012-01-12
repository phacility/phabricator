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
    if ($next_uri == '/login/') {
      $next_uri = '/';
    }

    if (!$request->isFormPost()) {
      $request->setCookie('next_uri', $next_uri);
    }

    $password_auth = PhabricatorEnv::getEnvConfig('auth.password-auth-enabled');

    $forms = array();


    $errors = array();
    if ($password_auth) {
      $require_captcha = false;
      $e_captcha = true;
      $username_or_email = $request->getCookie('phusr');
      if ($request->isFormPost()) {

        if (AphrontFormRecaptchaControl::isRecaptchaEnabled()) {
          $failed_attempts = PhabricatorUserLog::loadRecentEventsFromThisIP(
            PhabricatorUserLog::ACTION_LOGIN_FAILURE,
            60 * 15);
          if (count($failed_attempts) > 5) {
            $require_captcha = true;
            if (!AphrontFormRecaptchaControl::processCaptcha($request)) {
              if (AphrontFormRecaptchaControl::hasCaptchaResponse($request)) {
                $e_captcha = 'Invalid';
                $errors[] = 'CAPTCHA was not entered correctly.';
              } else {
                $e_captcha = 'Required';
                $errors[] = 'Too many login failures recently. You must '.
                            'submit a CAPTCHA with your login request.';
              }
            }
          }
        }

        $username_or_email = $request->getStr('username_or_email');

        $user = id(new PhabricatorUser())->loadOneWhere(
          'username = %s',
          $username_or_email);

        if (!$user) {
          $user = id(new PhabricatorUser())->loadOneWhere(
            'email = %s',
            $username_or_email);
        }

        if (!$errors) {
          // Perform username/password tests only if we didn't get rate limited
          // by the CAPTCHA.
          if (!$user || !$user->comparePassword($request->getStr('password'))) {
            $errors[] = 'Bad username/password.';
          }
        }

        if (!$errors) {
          $session_key = $user->establishSession('web');

          $request->setCookie('phusr', $user->getUsername());
          $request->setCookie('phsid', $session_key);

          $uri = new PhutilURI('/login/validate/');
          $uri->setQueryParams(
            array(
              'phusr' => $user->getUsername(),
            ));

          return id(new AphrontRedirectResponse())
            ->setURI((string)$uri);
        } else {
          $log = PhabricatorUserLog::newLog(
            null,
            $user,
            PhabricatorUserLog::ACTION_LOGIN_FAILURE);
          $log->save();

          $request->clearCookie('phusr');
          $request->clearCookie('phsid');
        }
      }

      if ($errors) {
        $error_view = new AphrontErrorView();
        $error_view->setTitle('Login Failed');
        $error_view->setErrors($errors);
      } else {
        $error_view = null;
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
                'Forgot your password? / Email Login</a>'));

      if ($require_captcha) {
        $form->appendChild(
          id(new AphrontFormRecaptchaControl())
            ->setError($e_captcha));
      }

      $form
        ->appendChild(
          id(new AphrontFormSubmitControl())
            ->setValue('Login'));


  //    $panel->setCreateButton('Register New Account', '/login/register/');
      $forms['Phabricator Login'] = $form;
    }

    $providers = PhabricatorOAuthProvider::getAllProviders();
    foreach ($providers as $provider) {
      $enabled = $provider->isProviderEnabled();
      if (!$enabled) {
        continue;
      }

      $auth_uri       = $provider->getAuthURI();
      $redirect_uri   = $provider->getRedirectURI();
      $client_id      = $provider->getClientID();
      $provider_name  = $provider->getProviderName();
      $minimum_scope  = $provider->getMinimumScope();
      $extra_auth     = $provider->getExtraAuthParameters();

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
        ->addHiddenInput('scope', $minimum_scope);

      foreach ($extra_auth as $key => $value) {
        $auth_form->addHiddenInput($key, $value);
      }

      $auth_form
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
