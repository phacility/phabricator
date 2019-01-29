<?php

final class PhabricatorTwilioFuture extends FutureProxy {

  private $future;
  private $accountSID;
  private $authToken;
  private $method;
  private $parameters;
  private $timeout;

  public function __construct() {
    parent::__construct(null);
  }

  public function setAccountSID($account_sid) {
    $this->accountSID = $account_sid;
    return $this;
  }

  public function setAuthToken(PhutilOpaqueEnvelope $token) {
    $this->authToken = $token;
    return $this;
  }

  public function setMethod($method, array $parameters) {
    $this->method = $method;
    $this->parameters = $parameters;
    return $this;
  }

  public function setTimeout($timeout) {
    $this->timeout = $timeout;
    return $this;
  }

  public function getTimeout() {
    return $this->timeout;
  }

  protected function getProxiedFuture() {
    if (!$this->future) {
      if ($this->accountSID === null) {
        throw new PhutilInvalidStateException('setAccountSID');
      }

      if ($this->authToken === null) {
        throw new PhutilInvalidStateException('setAuthToken');
      }

      if ($this->method === null || $this->parameters === null) {
        throw new PhutilInvalidStateException('setMethod');
      }

      $path = urisprintf(
        '/%s/Accounts/%s/%s',
        '2010-04-01',
        $this->accountSID,
        $this->method);

      $uri = id(new PhutilURI('https://api.twilio.com/'))
        ->setPath($path);

      $data = $this->parameters;

      $future = id(new HTTPSFuture($uri, $data))
        ->setHTTPBasicAuthCredentials($this->accountSID, $this->authToken)
        ->setMethod('POST')
        ->addHeader('Accept', 'application/json');

      $timeout = $this->getTimeout();
      if ($timeout) {
        $future->setTimeout($timeout);
      }

      $this->future = $future;
    }

    return $this->future;
  }

  protected function didReceiveResult($result) {
    list($status, $body, $headers) = $result;

    if ($status->isError()) {
      throw $status;
    }

    try {
      $data = phutil_json_decode($body);
    } catch (PhutilJSONParserException $ex) {
      throw new PhutilProxyException(
        pht('Expected JSON response from Twilio.'),
        $ex);
    }

    return $data;
  }

}
