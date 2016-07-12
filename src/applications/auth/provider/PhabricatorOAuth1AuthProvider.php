<?php

abstract class PhabricatorOAuth1AuthProvider
  extends PhabricatorOAuthAuthProvider {

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

  protected function configureAdapter(PhutilOAuth1AuthAdapter $adapter) {
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

      $this->saveHandshakeTokenSecret(
        $client_code,
        $adapter->getTokenSecret());

      $response = id(new AphrontRedirectResponse())
        ->setIsExternal(true)
        ->setURI($uri);
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
      throw new Exception(pht("Expected '%s' in request!", 'oauth_token'));
    }

    if (!$verifier) {
      throw new Exception(pht("Expected '%s' in request!", 'oauth_verifier'));
    }

    $adapter->setToken($token);
    $adapter->setVerifier($verifier);

    $client_code = $this->getAuthCSRFCode($request);
    $token_secret = $this->loadHandshakeTokenSecret($client_code);
    $adapter->setTokenSecret($token_secret);

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


/* -(  Temporary Secrets  )-------------------------------------------------- */


  private function saveHandshakeTokenSecret($client_code, $secret) {
    $secret_type = PhabricatorOAuth1SecretTemporaryTokenType::TOKENTYPE;
    $key = $this->getHandshakeTokenKeyFromClientCode($client_code);
    $type = $this->getTemporaryTokenType($secret_type);

    // Wipe out an existing token, if one exists.
    $token = id(new PhabricatorAuthTemporaryTokenQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withTokenResources(array($key))
      ->withTokenTypes(array($type))
      ->executeOne();
    if ($token) {
      $token->delete();
    }

    // Save the new secret.
    id(new PhabricatorAuthTemporaryToken())
      ->setTokenResource($key)
      ->setTokenType($type)
      ->setTokenExpires(time() + phutil_units('1 hour in seconds'))
      ->setTokenCode($secret)
      ->save();
  }

  private function loadHandshakeTokenSecret($client_code) {
    $secret_type = PhabricatorOAuth1SecretTemporaryTokenType::TOKENTYPE;
    $key = $this->getHandshakeTokenKeyFromClientCode($client_code);
    $type = $this->getTemporaryTokenType($secret_type);

    $token = id(new PhabricatorAuthTemporaryTokenQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withTokenResources(array($key))
      ->withTokenTypes(array($type))
      ->withExpired(false)
      ->executeOne();

    if (!$token) {
      throw new Exception(
        pht(
          'Unable to load your OAuth1 token secret from storage. It may '.
          'have expired. Try authenticating again.'));
    }

    return $token->getTokenCode();
  }

  private function getTemporaryTokenType($core_type) {
    // Namespace the type so that multiple providers don't step on each
    // others' toes if a user starts Mediawiki and Bitbucket auth at the
    // same time.

    // TODO: This isn't really a proper use of the table and should get
    // cleaned up some day: the type should be constant.

    return $core_type.':'.$this->getProviderConfig()->getID();
  }

  private function getHandshakeTokenKeyFromClientCode($client_code) {
    // NOTE: This is very slightly coersive since the TemporaryToken table
    // expects an "objectPHID" as an identifier, but nothing about the storage
    // is bound to PHIDs.

    return 'oauth1:secret/'.$client_code;
  }

}
