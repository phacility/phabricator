<?php

abstract class PhabricatorAuthProviderOAuth extends PhabricatorAuthProvider {

  protected $adapter;

  abstract protected function getOAuthClientID();
  abstract protected function getOAuthClientSecret();
  abstract protected function newOAuthAdapter();


  public function getDescriptionForCreate() {
    return pht('Configure %s OAuth.', $this->getProviderName());
  }

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

  public function isLoginFormAButton() {
    return true;
  }

  protected function renderLoginForm(AphrontRequest $request, $is_link) {
    $viewer = $request->getUser();

    if ($is_link) {
      $button_text = pht('Link External Account');
    } else if ($this->shouldAllowRegistration()) {
      $button_text = pht('Login or Register');
    } else {
      $button_text = pht('Login');
    }

    $icon = id(new PHUIIconView())
      ->setSpriteSheet(PHUIIconView::SPRITE_LOGIN)
      ->setSpriteIcon($this->getLoginIcon());

    $button = id(new PHUIButtonView())
        ->setSize(PHUIButtonView::BIG)
        ->setColor(PHUIButtonView::GREY)
        ->setIcon($icon)
        ->setText($button_text)
        ->setSubtext($this->getProviderName());

    $adapter = $this->getAdapter();
    $adapter->setState(PhabricatorHash::digest($request->getCookie('phcid')));

    $uri = new PhutilURI($adapter->getAuthenticateURI());
    $params = $uri->getQueryParams();
    $uri->setQueryParams(array());

    $content = array($button);

    foreach ($params as $key => $value) {
      $content[] = phutil_tag(
        'input',
        array(
          'type' => 'hidden',
          'name' => $key,
          'value' => $value,
        ));
    }

    return phabricator_form(
      $viewer,
      array(
        'method' => 'GET',
        'action' => (string)$uri,
      ),
      $content);
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
