<?php

abstract class PhabricatorAuthProviderOAuth1
  extends PhabricatorAuthProviderOAuth {

  protected $adapter;

  const PROPERTY_CONSUMER_KEY = 'oauth1:consumer:key';
  const PROPERTY_CONSUMER_SECRET = 'oauth1:consumer:secret';
  const PROPERTY_PRIVATE_KEY = 'oauth1:private:key';

  protected function getIDKey() {
    return self::PROPERTY_CONSUMER_KEY;
  }

  protected function getSecretKey() {
    return self::PROPERTY_CONSUMER_SECRET;
  }

  protected function configureAdapter(PhutilAuthAdapterOAuth1 $adapter) {
    $config = $this->getProviderConfig();
    $adapter->setConsumerKey($config->getProperty(self::PROPERTY_CONSUMER_KEY));
    $secret = $config->getProperty(self::PROPERTY_CONSUMER_SECRET);
    if (strlen($secret)) {
      $adapter->setConsumerSecret(new PhutilOpaqueEnvelope($secret));
    }
    $adapter->setCallbackURI(PhabricatorEnv::getURI($this->getLoginURI()));
    return $adapter;
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
      // Add a CSRF code to the callback URI, which we'll verify when
      // performing the login.

      $client_code = $this->getAuthCSRFCode($request);

      $callback_uri = $adapter->getCallbackURI();
      $callback_uri = $callback_uri.$client_code.'/';
      $adapter->setCallbackURI($callback_uri);

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

    $this->verifyAuthCSRFCode($request, $controller->getExtraURIData());

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

  public function processEditForm(
    AphrontRequest $request,
    array $values) {

    $key_ckey = self::PROPERTY_CONSUMER_KEY;
    $key_csecret = self::PROPERTY_CONSUMER_SECRET;

    return $this->processOAuthEditForm(
      $request,
      $values,
      pht('Consumer key is required.'),
      pht('Consumer secret is required.'));
  }

  public function extendEditForm(
    AphrontRequest $request,
    AphrontFormView $form,
    array $values,
    array $issues) {

    return $this->extendOAuthEditForm(
      $request,
      $form,
      $values,
      $issues,
      pht('OAuth Consumer Key'),
      pht('OAuth Consumer Secret'));
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
