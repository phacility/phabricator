<?php

final class PhabricatorAuthStartController
  extends PhabricatorAuthController {

  public function shouldRequireLogin() {
    return false;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getUser();

    if ($viewer->isLoggedIn()) {
      // Kick the user home if they are already logged in.
      return id(new AphrontRedirectResponse())->setURI('/');
    }

    if ($request->isAjax()) {
      return $this->processAjaxRequest();
    }

    if ($request->isConduit()) {
      return $this->processConduitRequest();
    }

    // If the user gets this far, they aren't logged in, so if they have a
    // user session token we can conclude that it's invalid: if it was valid,
    // they'd have been logged in above and never made it here. Try to clear
    // it and warn the user they may need to nuke their cookies.

    $session_token = $request->getCookie(PhabricatorCookies::COOKIE_SESSION);

    if (strlen($session_token)) {
      $kind = PhabricatorAuthSessionEngine::getSessionKindFromToken(
        $session_token);
      switch ($kind) {
        case PhabricatorAuthSessionEngine::KIND_ANONYMOUS:
          // If this is an anonymous session. It's expected that they won't
          // be logged in, so we can just continue.
          break;
        default:
          // The session cookie is invalid, so clear it.
          $request->clearCookie(PhabricatorCookies::COOKIE_USERNAME);
          $request->clearCookie(PhabricatorCookies::COOKIE_SESSION);

          return $this->renderError(
            pht(
              'Your login session is invalid. Try reloading the page and '.
              'logging in again. If that does not work, clear your browser '.
              'cookies.'));
      }
    }

    $providers = PhabricatorAuthProvider::getAllEnabledProviders();
    foreach ($providers as $key => $provider) {
      if (!$provider->shouldAllowLogin()) {
        unset($providers[$key]);
      }
    }

    if (!$providers) {
      if ($this->isFirstTimeSetup()) {
        // If this is a fresh install, let the user register their admin
        // account.
        return id(new AphrontRedirectResponse())
          ->setURI($this->getApplicationURI('/register/'));
      }

      return $this->renderError(
        pht(
          'This Phabricator install is not configured with any enabled '.
          'authentication providers which can be used to log in. If you '.
          'have accidentally locked yourself out by disabling all providers, '.
          'you can use `%s` to recover access to an administrative account.',
          'phabricator/bin/auth recover <username>'));
    }

    $next_uri = $request->getStr('next');
    if (!strlen($next_uri)) {
      if ($this->getDelegatingController()) {
        // Only set a next URI from the request path if this controller was
        // delegated to, which happens when a user tries to view a page which
        // requires them to login.

        // If this controller handled the request directly, we're on the main
        // login page, and never want to redirect the user back here after they
        // login.
        $next_uri = (string)$this->getRequest()->getRequestURI();
      }
    }

    if (!$request->isFormPost()) {
      if (strlen($next_uri)) {
        PhabricatorCookies::setNextURICookie($request, $next_uri);
      }
      PhabricatorCookies::setClientIDCookie($request);
    }

    if (!$request->getURIData('loggedout') && count($providers) == 1) {
      $auto_login_provider = head($providers);
      $auto_login_config = $auto_login_provider->getProviderConfig();
      if ($auto_login_provider instanceof PhabricatorPhabricatorAuthProvider &&
          $auto_login_config->getShouldAutoLogin()) {
        $auto_login_adapter = $provider->getAdapter();
        $auto_login_adapter->setState($provider->getAuthCSRFCode($request));
        return id(new AphrontRedirectResponse())
          ->setIsExternal(true)
          ->setURI($provider->getAdapter()->getAuthenticateURI());
      }
    }

    $invite = $this->loadInvite();

    $not_buttons = array();
    $are_buttons = array();
    $providers = msort($providers, 'getLoginOrder');
    foreach ($providers as $provider) {
      if ($invite) {
        $form = $provider->buildInviteForm($this);
      } else {
        $form = $provider->buildLoginForm($this);
      }
      if ($provider->isLoginFormAButton()) {
        $are_buttons[] = $form;
      } else {
        $not_buttons[] = $form;
      }
    }

    $out = array();
    $out[] = $not_buttons;
    if ($are_buttons) {
      require_celerity_resource('auth-css');

      foreach ($are_buttons as $key => $button) {
        $are_buttons[$key] = phutil_tag(
          'div',
          array(
            'class' => 'phabricator-login-button mmb',
          ),
          $button);
      }

      // If we only have one button, add a second pretend button so that we
      // always have two columns. This makes it easier to get the alignments
      // looking reasonable.
      if (count($are_buttons) == 1) {
        $are_buttons[] = null;
      }

      $button_columns = id(new AphrontMultiColumnView())
        ->setFluidLayout(true);
      $are_buttons = array_chunk($are_buttons, ceil(count($are_buttons) / 2));
      foreach ($are_buttons as $column) {
        $button_columns->addColumn($column);
      }

      $out[] = phutil_tag(
        'div',
        array(
          'class' => 'phabricator-login-buttons',
        ),
        $button_columns);
    }

    $handlers = PhabricatorAuthLoginHandler::getAllHandlers();

    $delegating_controller = $this->getDelegatingController();

    $header = array();
    foreach ($handlers as $handler) {
      $handler = clone $handler;

      $handler->setRequest($request);

      if ($delegating_controller) {
        $handler->setDelegatingController($delegating_controller);
      }

      $header[] = $handler->getAuthLoginHeaderContent();
    }

    $invite_message = null;
    if ($invite) {
      $invite_message = $this->renderInviteHeader($invite);
    }

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Login'));
    $crumbs->setBorder(true);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $header,
        $invite_message,
        $out,
      ),
      array(
        'title' => pht('Login to Phabricator'),
      ));
  }


  private function processAjaxRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    // We end up here if the user clicks a workflow link that they need to
    // login to use. We give them a dialog saying "You need to login...".

    if ($request->isDialogFormPost()) {
      return id(new AphrontRedirectResponse())->setURI(
        $request->getRequestURI());
    }

    $dialog = new AphrontDialogView();
    $dialog->setUser($viewer);
    $dialog->setTitle(pht('Login Required'));
    $dialog->appendChild(pht('You must login to continue.'));
    $dialog->addSubmitButton(pht('Login'));
    $dialog->addCancelButton('/');

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }


  private function processConduitRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    // A common source of errors in Conduit client configuration is getting
    // the request path wrong. The client will end up here, so make some
    // effort to give them a comprehensible error message.

    $request_path = $this->getRequest()->getPath();
    $conduit_path = '/api/<method>';
    $example_path = '/api/conduit.ping';

    $message = pht(
      'ERROR: You are making a Conduit API request to "%s", but the correct '.
      'HTTP request path to use in order to access a COnduit method is "%s" '.
      '(for example, "%s"). Check your configuration.',
      $request_path,
      $conduit_path,
      $example_path);

    return id(new AphrontPlainTextResponse())->setContent($message);
  }

  protected function renderError($message) {
    return $this->renderErrorPage(
      pht('Authentication Failure'),
      array($message));
  }

}
