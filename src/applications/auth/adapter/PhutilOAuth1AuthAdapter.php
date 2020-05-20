<?php

/**
 * Abstract adapter for OAuth1 providers.
 */
abstract class PhutilOAuth1AuthAdapter extends PhutilAuthAdapter {

  private $consumerKey;
  private $consumerSecret;
  private $token;
  private $tokenSecret;
  private $verifier;
  private $handshakeData;
  private $callbackURI;
  private $privateKey;

  public function setPrivateKey(PhutilOpaqueEnvelope $private_key) {
    $this->privateKey = $private_key;
    return $this;
  }

  public function getPrivateKey() {
    return $this->privateKey;
  }

  public function setCallbackURI($callback_uri) {
    $this->callbackURI = $callback_uri;
    return $this;
  }

  public function getCallbackURI() {
    return $this->callbackURI;
  }

  public function setVerifier($verifier) {
    $this->verifier = $verifier;
    return $this;
  }

  public function getVerifier() {
    return $this->verifier;
  }

  public function setConsumerSecret(PhutilOpaqueEnvelope $consumer_secret) {
    $this->consumerSecret = $consumer_secret;
    return $this;
  }

  public function getConsumerSecret() {
    return $this->consumerSecret;
  }

  public function setConsumerKey($consumer_key) {
    $this->consumerKey = $consumer_key;
    return $this;
  }

  public function getConsumerKey() {
    return $this->consumerKey;
  }

  public function setTokenSecret($token_secret) {
    $this->tokenSecret = $token_secret;
    return $this;
  }

  public function getTokenSecret() {
    return $this->tokenSecret;
  }

  public function setToken($token) {
    $this->token = $token;
    return $this;
  }

  public function getToken() {
    return $this->token;
  }

  protected function getHandshakeData() {
    if ($this->handshakeData === null) {
      $this->finishOAuthHandshake();
    }
    return $this->handshakeData;
  }

  abstract protected function getRequestTokenURI();
  abstract protected function getAuthorizeTokenURI();
  abstract protected function getValidateTokenURI();

  protected function getSignatureMethod() {
    return 'HMAC-SHA1';
  }

  public function getContentSecurityPolicyFormActions() {
    return array(
      $this->getAuthorizeTokenURI(),
    );
  }

  protected function newOAuth1Future($uri, $data = array()) {
    $future = id(new PhutilOAuth1Future($uri, $data))
      ->setMethod('POST')
      ->setSignatureMethod($this->getSignatureMethod());

    $consumer_key = $this->getConsumerKey();
    if (strlen($consumer_key)) {
      $future->setConsumerKey($consumer_key);
    } else {
      throw new Exception(
        pht(
          '%s is required!',
          'setConsumerKey()'));
    }

    $consumer_secret = $this->getConsumerSecret();
    if ($consumer_secret) {
      $future->setConsumerSecret($consumer_secret);
    }

    if (strlen($this->getToken())) {
      $future->setToken($this->getToken());
    }

    if (strlen($this->getTokenSecret())) {
      $future->setTokenSecret($this->getTokenSecret());
    }

    if ($this->getPrivateKey()) {
      $future->setPrivateKey($this->getPrivateKey());
    }

    return $future;
  }

  public function getClientRedirectURI() {
    $request_token_uri = $this->getRequestTokenURI();

    $future = $this->newOAuth1Future($request_token_uri);
    if (strlen($this->getCallbackURI())) {
      $future->setCallbackURI($this->getCallbackURI());
    }

    list($body) = $future->resolvex();
    $data = id(new PhutilQueryStringParser())->parseQueryString($body);

    // NOTE: Per the spec, this value MUST be the string 'true'.
    $confirmed = idx($data, 'oauth_callback_confirmed');
    if ($confirmed !== 'true') {
      throw new Exception(
        pht("Expected '%s' to be '%s'!", 'oauth_callback_confirmed', 'true'));
    }

    $this->readTokenAndTokenSecret($data);

    $authorize_token_uri = new PhutilURI($this->getAuthorizeTokenURI());
    $authorize_token_uri->replaceQueryParam('oauth_token', $this->getToken());

    return phutil_string_cast($authorize_token_uri);
  }

  protected function finishOAuthHandshake() {
    $this->willFinishOAuthHandshake();

    if (!$this->getToken()) {
      throw new Exception(pht('Expected token to finish OAuth handshake!'));
    }
    if (!$this->getVerifier()) {
      throw new Exception(pht('Expected verifier to finish OAuth handshake!'));
    }

    $validate_uri = $this->getValidateTokenURI();
    $params = array(
      'oauth_verifier' => $this->getVerifier(),
    );

    list($body) = $this->newOAuth1Future($validate_uri, $params)->resolvex();
    $data = id(new PhutilQueryStringParser())->parseQueryString($body);

    $this->readTokenAndTokenSecret($data);

    $this->handshakeData = $data;
  }

  private function readTokenAndTokenSecret(array $data) {
    $token = idx($data, 'oauth_token');
    if (!$token) {
      throw new Exception(pht("Expected '%s' in response!", 'oauth_token'));
    }

    $token_secret = idx($data, 'oauth_token_secret');
    if (!$token_secret) {
      throw new Exception(
        pht("Expected '%s' in response!", 'oauth_token_secret'));
    }

    $this->setToken($token);
    $this->setTokenSecret($token_secret);

    return $this;
  }

  /**
   * Hook that allows subclasses to take actions before the OAuth handshake
   * is completed.
   */
  protected function willFinishOAuthHandshake() {
    return;
  }

}
