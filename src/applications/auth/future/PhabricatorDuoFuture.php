<?php

final class PhabricatorDuoFuture
  extends FutureProxy {

  private $future;

  private $integrationKey;
  private $secretKey;
  private $apiHostname;

  private $httpMethod = 'POST';
  private $method;
  private $parameters;
  private $timeout;

  public function __construct() {
    parent::__construct(null);
  }

  public function setIntegrationKey($integration_key) {
    $this->integrationKey = $integration_key;
    return $this;
  }

  public function setSecretKey(PhutilOpaqueEnvelope $key) {
    $this->secretKey = $key;
    return $this;
  }

  public function setAPIHostname($hostname) {
    $this->apiHostname = $hostname;
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

  public function setHTTPMethod($method) {
    $this->httpMethod = $method;
    return $this;
  }

  public function getHTTPMethod() {
    return $this->httpMethod;
  }

  protected function getProxiedFuture() {
    if (!$this->future) {
      if ($this->integrationKey === null) {
        throw new PhutilInvalidStateException('setIntegrationKey');
      }

      if ($this->secretKey === null) {
        throw new PhutilInvalidStateException('setSecretKey');
      }

      if ($this->apiHostname === null) {
        throw new PhutilInvalidStateException('setAPIHostname');
      }

      if ($this->method === null || $this->parameters === null) {
        throw new PhutilInvalidStateException('setMethod');
      }

      $path = (string)urisprintf('/auth/v2/%s', $this->method);

      $host = $this->apiHostname;
      $host = phutil_utf8_strtolower($host);

      $data = $this->parameters;
      $date = date('r');

      $http_method = $this->getHTTPMethod();

      ksort($data);
      $data_parts = phutil_build_http_querystring($data);

      $corpus = array(
        $date,
        $http_method,
        $host,
        $path,
        $data_parts,
      );
      $corpus = implode("\n", $corpus);

      $signature = hash_hmac(
        'sha1',
        $corpus,
        $this->secretKey->openEnvelope());
      $signature = new PhutilOpaqueEnvelope($signature);

      if ($http_method === 'GET') {
        $uri_data = $data;
        $body_data = array();
      } else {
        $uri_data = array();
        $body_data = $data;
      }

      $uri = id(new PhutilURI('', $uri_data))
        ->setProtocol('https')
        ->setDomain($host)
        ->setPath($path);

      $future = id(new HTTPSFuture($uri, $body_data))
        ->setHTTPBasicAuthCredentials($this->integrationKey, $signature)
        ->setMethod($http_method)
        ->addHeader('Accept', 'application/json')
        ->addHeader('Date', $date);

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
        pht('Expected JSON response from Duo.'),
        $ex);
    }

    return $data;
  }

}
