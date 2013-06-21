<?php

abstract class PhabricatorAuthProviderOAuth extends PhabricatorAuthProvider {

  protected $adapter;

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

  protected function configureAdapter(PhutilAuthAdapterOAuth $adapter) {
    $config = $this->getProviderConfig();
    $adapter->setClientID($config->getProperty(self::PROPERTY_APP_ID));
    $adapter->setClientSecret(
      new PhutilOpaqueEnvelope(
        $config->getProperty(self::PROPERTY_APP_SECRET)));
    $adapter->setRedirectURI($this->getLoginURI());
    return $adapter;
  }

  public function isLoginFormAButton() {
    return true;
  }

  protected function renderLoginForm(AphrontRequest $request, $mode) {
    $viewer = $request->getUser();

    if ($mode == 'link') {
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

  const PROPERTY_APP_ID = 'oauth:app:id';
  const PROPERTY_APP_SECRET = 'oauth:app:secret';

  public function readFormValuesFromProvider() {
    $config = $this->getProviderConfig();
    $id = $config->getProperty(self::PROPERTY_APP_ID);
    $secret = $config->getProperty(self::PROPERTY_APP_SECRET);

    return array(
      self::PROPERTY_APP_ID     => $id,
      self::PROPERTY_APP_SECRET => $secret,
    );
  }

  public function readFormValuesFromRequest(AphrontRequest $request) {
    return array(
      self::PROPERTY_APP_ID     => $request->getStr(self::PROPERTY_APP_ID),
      self::PROPERTY_APP_SECRET => $request->getStr(self::PROPERTY_APP_SECRET),
    );
  }

  public function processEditForm(
    AphrontRequest $request,
    array $values) {
    $errors = array();
    $issues = array();

    $key_id = self::PROPERTY_APP_ID;
    $key_secret = self::PROPERTY_APP_SECRET;

    if (!strlen($values[$key_id])) {
      $errors[] = pht('Application ID is required.');
      $issues[$key_id] = pht('Required');
    }

    if (!strlen($values[$key_secret])) {
      $errors[] = pht('Application secret is required.');
      $issues[$key_secret] = pht('Required');
    }

    // If the user has not changed the secret, don't update it (that is,
    // don't cause a bunch of "****" to be written to the database).
    if (preg_match('/^[*]+$/', $values[$key_secret])) {
      unset($values[$key_secret]);
    }

    return array($errors, $issues, $values);
  }

  public function extendEditForm(
    AphrontRequest $request,
    AphrontFormView $form,
    array $values,
    array $issues) {

    $key_id = self::PROPERTY_APP_ID;
    $key_secret = self::PROPERTY_APP_SECRET;

    $v_id = $values[$key_id];
    $v_secret = $values[$key_secret];
    if ($v_secret) {
      $v_secret = str_repeat('*', strlen($v_secret));
    }

    $e_id = idx($issues, $key_id, $request->isFormPost() ? null : true);
    $e_secret = idx($issues, $key_secret, $request->isFormPost() ? null : true);

    $form
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('OAuth App ID'))
          ->setName($key_id)
          ->setValue($v_id)
          ->setError($e_id))
      ->appendChild(
        id(new AphrontFormPasswordControl())
          ->setLabel(pht('OAuth App Secret'))
          ->setName($key_secret)
          ->setValue($v_secret)
          ->setError($e_secret));
  }

  public function renderConfigPropertyTransactionTitle(
    PhabricatorAuthProviderConfigTransaction $xaction) {

    $author_phid = $xaction->getAuthorPHID();
    $old = $xaction->getOldValue();
    $new = $xaction->getNewValue();
    $key = $xaction->getMetadataValue(
      PhabricatorAuthProviderConfigTransaction::PROPERTY_KEY);

    switch ($key) {
      case self::PROPERTY_APP_ID:
        if (strlen($old)) {
          return pht(
            '%s updated the OAuth application ID for this provider from '.
            '"%s" to "%s".',
            $xaction->renderHandleLink($author_phid),
            $old,
            $new);
        } else {
          return pht(
            '%s set the OAuth application ID for this provider to '.
            '"%s".',
            $xaction->renderHandleLink($author_phid),
            $new);
        }
      case self::PROPERTY_APP_SECRET:
        if (strlen($old)) {
          return pht(
            '%s updated the OAuth application secret for this provider.',
            $xaction->renderHandleLink($author_phid));
        } else {
          return pht(
            '%s set the OAuth application seceret for this provider.',
            $xaction->renderHandleLink($author_phid));
        }
    }

    return parent::renderConfigPropertyTransactionTitle($xaction);
  }

}
