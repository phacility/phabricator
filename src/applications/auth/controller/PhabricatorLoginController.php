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

final class PhabricatorLoginController
  extends PhabricatorAuthController {

  public function shouldRequireLogin() {
    return false;
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    if ($user->isLoggedIn()) {
      // Kick the user out if they're already logged in.
      return id(new AphrontRedirectResponse())->setURI('/');
    }

    if ($request->isAjax()) {

      // We end up here if the user clicks a workflow link that they need to
      // login to use. We give them a dialog saying "You need to login..".

      if ($request->isDialogFormPost()) {
        return id(new AphrontRedirectResponse())->setURI(
          $request->getRequestURI());
      }

      $dialog = new AphrontDialogView();
      $dialog->setUser($user);
      $dialog->setTitle('Login Required');
      $dialog->appendChild('<p>You must login to continue.</p>');
      $dialog->addSubmitButton('Login');
      $dialog->addCancelButton('/', 'Cancel');

      return id(new AphrontDialogResponse())->setDialog($dialog);
    }

    if ($request->isConduit()) {

      // A common source of errors in Conduit client configuration is getting
      // the request path wrong. The client will end up here, so make some
      // effort to give them a comprehensible error message.

      $request_path = $this->getRequest()->getPath();
      $conduit_path = '/api/<method>';
      $example_path = '/api/conduit.ping';

      $message =
        "ERROR: You are making a Conduit API request to '{$request_path}', ".
        "but the correct HTTP request path to use in order to access a ".
        "Conduit method is '{$conduit_path}' (for example, ".
        "'{$example_path}'). Check your configuration.";

      return id(new AphrontPlainTextResponse())->setContent($message);
    }

    $error_view = null;
    if ($request->getCookie('phusr') && $request->getCookie('phsid')) {
      // The session cookie is invalid, so clear it.
      $request->clearCookie('phusr');
      $request->clearCookie('phsid');

      $error_view = new AphrontErrorView();
      $error_view->setTitle('Invalid Session');
      $error_view->setErrors(array(
        "Your login session is invalid. Try logging in again. If that ".
        "doesn't work, clear your browser cookies."
      ));
    }

    $next_uri_path = $this->getRequest()->getPath();
    if ($next_uri_path == '/login/') {
      $next_uri = '/';
    } else {
      $next_uri = $this->getRequest()->getRequestURI();
    }

    if (!$request->isFormPost()) {
      $request->setCookie('next_uri', $next_uri);
    }

    $password_auth = PhabricatorEnv::getEnvConfig('auth.password-auth-enabled');

    $username_or_email = $request->getCookie('phusr');

    $forms = array();

    $errors = array();
    if ($password_auth) {
      $require_captcha = false;
      $e_captcha = true;
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
          $user = PhabricatorUser::loadOneWithEmailAddress($username_or_email);
        }

        if (!$errors) {
          // Perform username/password tests only if we didn't get rate limited
          // by the CAPTCHA.

          $envelope = new PhutilOpaqueEnvelope($request->getStr('password'));

          if (!$user || !$user->comparePassword($envelope)) {
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

    $ldap_provider = new PhabricatorLDAPProvider();
    if ($ldap_provider->isProviderEnabled()) {
      $ldap_form = new AphrontFormView();
      $ldap_form
        ->setUser($request->getUser())
        ->setAction('/ldap/login/')
        ->appendChild(
          id(new AphrontFormTextControl())
          ->setLabel('LDAP username')
          ->setName('username')
          ->setValue($username_or_email))
        ->appendChild(
          id(new AphrontFormPasswordControl())
          ->setLabel('Password')
          ->setName('password'));

      $ldap_form
        ->appendChild(
          id(new AphrontFormSubmitControl())
          ->setValue('Login'));

      $forms['LDAP Login'] = $ldap_form;
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
        $body = 'Login or register for Phabricator using your '.
                phutil_escape_html($provider_name).' account.';
        $button = "Login or Register with {$provider_name}";
      } else {
        $title = "Login with {$provider_name}";
        $body = 'Login to your existing Phabricator account using your '.
                phutil_escape_html($provider_name).' account.<br /><br />'.
                '<strong>You can not use '.
                phutil_escape_html($provider_name).' to register a new '.
                'account.</strong>';
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

    $login_message = PhabricatorEnv::getEnvConfig('auth.login-message');

    return $this->buildStandardPageResponse(
      array(
        $error_view,
        $login_message,
        $panel,
      ),
      array(
        'title' => 'Login',
      ));
  }

}
