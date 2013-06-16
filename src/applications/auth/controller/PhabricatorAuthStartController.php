<?php

final class PhabricatorAuthStartController
  extends PhabricatorAuthController {

  public function shouldRequireLogin() {
    return false;
  }

  public function processRequest() {
    $request = $this->getRequest();
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

    if ($request->getCookie('phusr') && $request->getCookie('phsid')) {
      // The session cookie is invalid, so clear it.
      $request->clearCookie('phusr');
      $request->clearCookie('phsid');

      return $this->renderError(
        pht(
          "Your login session is invalid. Try reloading the page and logging ".
          "in again. If that does not work, clear your browser cookies."));
    }


    $providers = PhabricatorAuthProvider::getAllEnabledProviders();
    foreach ($providers as $key => $provider) {
      if (!$provider->shouldAllowLogin()) {
        unset($providers[$key]);
      }
    }

    if (!$providers) {
      return $this->renderError(
        pht(
          "This Phabricator install is not configured with any enabled ".
          "authentication providers which can be used to log in."));
    }

    $next_uri = $request->getStr('next');
    if (!$next_uri) {
      $next_uri_path = $this->getRequest()->getPath();
      if ($next_uri_path == '/auth/start/') {
        $next_uri = '/';
      } else {
        $next_uri = $this->getRequest()->getRequestURI();
      }
    }

    if (!$request->isFormPost()) {
      $request->setCookie('next_uri', $next_uri);
    }

    $out = array();
    foreach ($providers as $provider) {
      $out[] = $provider->buildLoginForm($this);
    }

    $login_message = PhabricatorEnv::getEnvConfig('auth.login-message');
    $login_message = phutil_safe_html($login_message);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName(pht('Login')));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $login_message,
        $out,
      ),
      array(
        'title' => pht('Login to Phabricator'),
        'device' => true,
        'dust' => true,
      ));
  }


  private function processAjaxRequest() {
    $request = $this->getRequest();
    $viewer = $request->getViewer();

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
    $viewer = $request->getViewer();

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

  private function renderError($message) {
    $title = pht('Authentication Failure');

    $view = new AphrontErrorView();
    $view->setTitle($title);
    $view->appendChild($message);

    return $this->buildApplicationPage(
      $view,
      array(
        'title' => $title,
        'device' => true,
        'dust' => true,
      ));
  }


}
