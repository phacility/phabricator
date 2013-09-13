<?php

abstract class PhabricatorAuthProviderOAuth1 extends PhabricatorAuthProvider {

  protected $adapter;

  const PROPERTY_CONSUMER_KEY = 'oauth1:consumer:key';
  const PROPERTY_CONSUMER_SECRET = 'oauth1:consumer:secret';
  const PROPERTY_PRIVATE_KEY = 'oauth1:private:key';

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

  protected function configureAdapter(PhutilAuthAdapterOAuth1 $adapter) {
    $config = $this->getProviderConfig();
    $adapter->setConsumerKey($config->getProperty(self::PROPERTY_CONSUMER_KEY));
    $secret = $config->getProperty(self::PROPERTY_CONSUMER_SECRET);
    if (strlen($secret)) {
      $adapter->setConsumerSecret(new PhutilOpaqueEnvelope($secret));
    }
    $adapter->setCallbackURI($this->getLoginURI());
    return $adapter;
  }

  public function isLoginFormAButton() {
    return true;
  }

  protected function renderLoginForm(AphrontRequest $request, $mode) {
    $attributes = array(
      'method' => 'POST',
      'uri' => $this->getLoginURI(),
    );
    return $this->renderStandardLoginButton($request, $mode, $attributes);
  }

  public function processLoginRequest(
    PhabricatorAuthLoginController $controller) {

    $request = $controller->getRequest();
    $adapter = $this->getAdapter();
    $account = null;
    $response = null;

    if ($request->isHTTPPost()) {
      $uri = $adapter->getClientRedirectURI();
      $response = id(new AphrontRedirectResponse())->setURI($uri);
      return array($account, $response);
    }

    $denied = $request->getStr('denied');
    if (strlen($denied)) {
      // Twitter indicates that the user cancelled the login attempt by
      // returning "denied" as a parameter.
      throw new PhutilAuthUserAbortedException();
    }

    // NOTE: You can get here via GET, this should probably be a bit more
    // user friendly.

    $token = $request->getStr('oauth_token');
    $verifier = $request->getStr('oauth_verifier');

    if (!$token) {
      throw new Exception("Expected 'oauth_token' in request!");
    }

    if (!$verifier) {
      throw new Exception("Expected 'oauth_verifier' in request!");
    }

    $adapter->setToken($token);
    $adapter->setVerifier($verifier);

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

  public function readFormValuesFromProvider() {
    $config = $this->getProviderConfig();
    $id = $config->getProperty(self::PROPERTY_CONSUMER_KEY);
    $secret = $config->getProperty(self::PROPERTY_CONSUMER_SECRET);

    return array(
      self::PROPERTY_CONSUMER_KEY => $id,
      self::PROPERTY_CONSUMER_SECRET => $secret,
    );
  }

  public function readFormValuesFromRequest(AphrontRequest $request) {
    return array(
      self::PROPERTY_CONSUMER_KEY
        => $request->getStr(self::PROPERTY_CONSUMER_KEY),
      self::PROPERTY_CONSUMER_SECRET
        => $request->getStr(self::PROPERTY_CONSUMER_SECRET),
    );
  }

  public function processEditForm(
    AphrontRequest $request,
    array $values) {
    $errors = array();
    $issues = array();

    $key_ckey = self::PROPERTY_CONSUMER_KEY;
    $key_csecret = self::PROPERTY_CONSUMER_SECRET;

    if (!strlen($values[$key_ckey])) {
      $errors[] = pht('Consumer key is required.');
      $issues[$key_ckey] = pht('Required');
    }

    if (!strlen($values[$key_csecret])) {
      $errors[] = pht('Consumer secret is required.');
      $issues[$key_csecret] = pht('Required');
    }

    // If the user has not changed the secret, don't update it (that is,
    // don't cause a bunch of "****" to be written to the database).
    if (preg_match('/^[*]+$/', $values[$key_csecret])) {
      unset($values[$key_csecret]);
    }

    return array($errors, $issues, $values);
  }

  public function extendEditForm(
    AphrontRequest $request,
    AphrontFormView $form,
    array $values,
    array $issues) {

    $key_id = self::PROPERTY_CONSUMER_KEY;
    $key_secret = self::PROPERTY_CONSUMER_SECRET;

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
          ->setLabel(pht('OAuth Consumer Key'))
          ->setName($key_id)
          ->setValue($v_id)
          ->setError($e_id))
      ->appendChild(
        id(new AphrontFormPasswordControl())
          ->setLabel(pht('OAuth Consumer Secret'))
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
      case self::PROPERTY_CONSUMER_KEY:
        if (strlen($old)) {
          return pht(
            '%s updated the OAuth consumer key for this provider from '.
            '"%s" to "%s".',
            $xaction->renderHandleLink($author_phid),
            $old,
            $new);
        } else {
          return pht(
            '%s set the OAuth consumer key for this provider to '.
            '"%s".',
            $xaction->renderHandleLink($author_phid),
            $new);
        }
      case self::PROPERTY_CONSUMER_SECRET:
        if (strlen($old)) {
          return pht(
            '%s updated the OAuth consumer secret for this provider.',
            $xaction->renderHandleLink($author_phid));
        } else {
          return pht(
            '%s set the OAuth consumer secret for this provider.',
            $xaction->renderHandleLink($author_phid));
        }
    }

    return parent::renderConfigPropertyTransactionTitle($xaction);
  }

  protected function willSaveAccount(PhabricatorExternalAccount $account) {
    parent::willSaveAccount($account);
    $this->synchronizeOAuthAccount($account);
  }

  protected function synchronizeOAuthAccount(
    PhabricatorExternalAccount $account) {
    $adapter = $this->getAdapter();

    $oauth_token = $adapter->getToken();
    $oauth_token_secret = $adapter->getTokenSecret();

    $account->setProperty('oauth1.token', $oauth_token);
    $account->setProperty('oauth1.token.secret', $oauth_token_secret);
  }

  public function willRenderLinkedAccount(
    PhabricatorUser $viewer,
    PHUIObjectItemView $item,
    PhabricatorExternalAccount $account) {

    $item->addAttribute(pht('OAuth1 Account'));

    parent::willRenderLinkedAccount($viewer, $item, $account);
  }


}
