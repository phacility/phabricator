<?php

abstract class PhabricatorAuthProviderOAuth extends PhabricatorAuthProvider {

  protected $adapter;

  abstract protected function getOAuthClientID();
  abstract protected function getOAuthClientSecret();
  abstract protected function newOAuthAdapter();

  public function getAdapter() {
    if (!$this->adapter) {
      $adapter = $this->newOAuthAdapter();
      $this->adapter = $adapter;
      $this->configureAdapter($adapter);
    }
    return $this->adapter;
  }

  public function isEnabled() {
    return parent::isEnabled() &&
           $this->getOAuthClientID() &&
           $this->getOAuthClientSecret();
  }

  protected function configureAdapter(PhutilAuthAdapterOAuth $adapter) {
    if ($this->getOAuthClientID()) {
      $adapter->setClientID($this->getOAuthClientID());
    }

    if ($this->getOAuthClientSecret()) {
      $adapter->setClientSecret($this->getOAuthClientSecret());
    }

    $adapter->setRedirectURI($this->getLoginURI());
    return $adapter;
  }

  public function buildLoginForm(
    PhabricatorAuthStartController $controller) {

    $request = $controller->getRequest();
    $viewer = $request->getUser();

    $form = id(new AphrontFormView())
      ->setUser($viewer);

    $submit = new AphrontFormSubmitControl();

    if ($this->shouldAllowRegistration()) {
      $submit->setValue(
        pht("Login or Register with %s \xC2\xBB", $this->getProviderName()));
      $header = pht("Login or Register with %s", $this->getProviderName());
    } else {
      $submit->setValue(
        pht("Login with %s \xC2\xBB", $this->getProviderName()));
      $header = pht("Login with %s", $this->getProviderName());
    }

    $form->appendChild($submit);

    // TODO: This is pretty hideous.
    $panel = new AphrontPanelView();
    $panel->setHeader($header);
    $panel->setWidth(AphrontPanelView::WIDTH_FORM);
    $panel->setNoBackground(true);
    $panel->appendChild($form);

    $adapter = $this->getAdapter();
    $adapter->setState(PhabricatorHash::digest($request->getCookie('phcid')));

    $uri = new PhutilURI($adapter->getAuthenticateURI());
    $params = $uri->getQueryParams();
    $uri->setQueryParams(array());

    $form->setAction((string)$uri);
    foreach ($params as $key => $value) {
      $form->addHiddenInput($key, $value);
    }

    $form->setMethod('GET');

    return $panel;
  }

  public function processLoginRequest(
    PhabricatorAuthLoginController $controller) {

    $request = $controller->getRequest();
    $adapter = $this->getAdapter();
    $account = null;
    $response = null;

    $error = $request->getStr('error');
    if ($error) {
      $response = $controller->buildProviderErrorResponse(
        $this,
        pht(
          'The OAuth provider returned an error: %s',
          $error));

      return array($account, $response);
    }

    $code = $request->getStr('code');
    if (!strlen($code)) {
      $response = $controller->buildProviderErrorResponse(
        $this,
        pht(
          'The OAuth provider did not return a "code" parameter in its '.
          'response.'));

      return array($account, $response);
    }

    if ($adapter->supportsStateParameter()) {
      $phcid = $request->getCookie('phcid');
      if (!strlen($phcid)) {
        $response = $controller->buildProviderErrorResponse(
          $this,
          pht(
            'Your browser did not submit a "phcid" cookie with OAuth state '.
            'information in the request. Check that cookies are enabled. '.
            'If this problem persists, you may need to clear your cookies.'));
      }

      $state = $request->getStr('state');
      $expect = PhabricatorHash::digest($phcid);
      if ($state !== $expect) {
        $response = $controller->buildProviderErrorResponse(
          $this,
          pht(
            'The OAuth provider did not return the correct "state" parameter '.
            'in its response. If this problem persists, you may need to clear '.
            'your cookies.'));
      }
    }

    $adapter->setCode($code);

    // NOTE: As a side effect, this will cause the OAuth adapter to request
    // an access token.

    try {
      $account_id = $adapter->getAccountID();
    } catch (Exception $ex) {
      // TODO: Handle this in a more user-friendly way.
      throw $ex;
    }

    if (!strlen($account_id)) {
      $response = $controller->buildProviderErrorResponse(
        $this,
        pht(
          'The OAuth provider failed to retrieve an account ID.'));

      return array($account, $response);
    }

    return array($this->loadOrCreateAccount($account_id), $response);
  }

}
