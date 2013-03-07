<?php

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
      $dialog->setTitle(pht('Login Required'));
      $dialog->appendChild(phutil_tag('p', array(), pht(
        'You must login to continue.')));
      $dialog->addSubmitButton(pht('Login'));
      $dialog->addCancelButton('/', pht('Cancel'));

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
      $error_view->setTitle(pht('Invalid Session'));
      $error_view->setErrors(array(
        pht("Your login session is invalid. Try logging in again. If that ".
        "doesn't work, clear your browser cookies.")
      ));
    }


    $next_uri = $request->getStr('next');
    if (!$next_uri) {
      $next_uri_path = $this->getRequest()->getPath();
      if ($next_uri_path == '/login/') {
        $next_uri = '/';
      } else {
        $next_uri = $this->getRequest()->getRequestURI();
      }
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
                $e_captcha = pht('Invalid');
                $errors[] = pht('CAPTCHA was not entered correctly.');
              } else {
                $e_captcha = pht('Required');
                $errors[] = pht('Too many login failures recently. You must '.
                            'submit a CAPTCHA with your login request.');
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
            $errors[] = pht('Bad username/password.');
          }
        }

        if (!$errors) {
          $session_key = $user->establishSession('web');

          $request->setCookie('phusr', $user->getUsername());
          $request->setCookie('phsid', $session_key);

          $uri = id(new PhutilURI('/login/validate/'))
            ->setQueryParams(
              array('phusr' => $user->getUsername()
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
        $error_view->setTitle(pht('Login Failed'));
        $error_view->setErrors($errors);
      }

      $form = new AphrontFormView();
      $form
        ->setUser($request->getUser())
        ->setAction('/login/')
        ->appendChild(
          id(new AphrontFormTextControl())
            ->setLabel(pht('Username/Email'))
            ->setName('username_or_email')
            ->setValue($username_or_email))
        ->appendChild(
          id(new AphrontFormPasswordControl())
            ->setLabel(pht('Password'))
            ->setName('password')
            ->setCaption(hsprintf(
              '<a href="/login/email/">%s</a>',
              pht('Forgot your password? / Email Login'))));

      if ($require_captcha) {
        $form->appendChild(
          id(new AphrontFormRecaptchaControl())
            ->setError($e_captcha));
      }

      $form
        ->appendChild(
          id(new AphrontFormSubmitControl())
            ->setValue(pht('Login')));


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
          ->setLabel(pht('LDAP username'))
          ->setName('username')
          ->setValue($username_or_email))
        ->appendChild(
          id(new AphrontFormPasswordControl())
          ->setLabel(pht('Password'))
          ->setName('password'));

      $ldap_form
        ->appendChild(
          id(new AphrontFormSubmitControl())
          ->setValue(pht('Login')));

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
        $title = pht("Login or Register with %s", $provider_name);
        $body = pht('Login or register for Phabricator using your %s account.',
          $provider_name);
        $button = pht("Login or Register with %s", $provider_name);
      } else {
        $title = pht("Login with %s", $provider_name);
        $body = hsprintf(
          '%s<br /><br /><strong>%s</strong>',
          pht(
            'Login to your existing Phabricator account using your %s account.',
            $provider_name),
          pht(
            'You can not use %s to register a new account.',
            $provider_name));
        $button = pht("Log in with %s", $provider_name);
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
        ->appendChild(hsprintf(
          '<p class="aphront-form-instructions">%s</p>',
          $body))
        ->appendChild(
          id(new AphrontFormSubmitControl())
            ->setValue("{$button} \xC2\xBB"));

      $forms[$title] = $auth_form;
    }

    $panel = new AphrontPanelView();
    $panel->setWidth(AphrontPanelView::WIDTH_FORM);
    $panel->setNoBackground();
    foreach ($forms as $name => $form) {
      $panel->appendChild(phutil_tag('h1', array(), $name));
      $panel->appendChild($form);
      $panel->appendChild(phutil_tag('br'));
    }

    $login_message = PhabricatorEnv::getEnvConfig('auth.login-message');

    return $this->buildApplicationPage(
      array(
        $error_view,
        phutil_safe_html($login_message),
        $panel,
      ),
      array(
        'title' => pht('Login'),
        'device' => true
      ));
  }

}
