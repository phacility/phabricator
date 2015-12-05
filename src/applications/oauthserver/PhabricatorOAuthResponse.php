<?php

final class PhabricatorOAuthResponse extends AphrontResponse {

  private $state;
  private $content;
  private $clientURI;
  private $error;
  private $errorDescription;

  private function getState() {
    return $this->state;
  }
  public function setState($state) {
    $this->state = $state;
    return $this;
  }

  private function getContent() {
    return $this->content;
  }
  public function setContent($content) {
    $this->content = $content;
    return $this;
  }

  private function getClientURI() {
    return $this->clientURI;
  }
  public function setClientURI(PhutilURI $uri) {
    $this->setHTTPResponseCode(302);
    $this->clientURI = $uri;
    return $this;
  }
  private function getFullURI() {
    $base_uri     = $this->getClientURI();
    $query_params = $this->buildResponseDict();
    foreach ($query_params as $key => $value) {
      $base_uri->setQueryParam($key, $value);
    }
    return $base_uri;
  }

  private function getError() {
    return $this->error;
  }

  public function setError($error) {
    // errors sometimes redirect to the client (302) but otherwise
    // the spec says all code 400
    if (!$this->getClientURI()) {
      $this->setHTTPResponseCode(400);
    }
    $this->error = $error;
    return $this;
  }

  private function getErrorDescription() {
    return $this->errorDescription;
  }

  public function setErrorDescription($error_description) {
    $this->errorDescription = $error_description;
    return $this;
  }

  public function __construct() {
    $this->setHTTPResponseCode(200); // assume the best
  }

  public function getHeaders() {
    $headers = array(
      array('Content-Type', 'application/json'),
    );
    if ($this->getClientURI()) {
      $headers[] = array('Location', $this->getFullURI());
    }
    // TODO -- T844 set headers with X-Auth-Scopes, etc
    $headers = array_merge(parent::getHeaders(), $headers);
    return $headers;
  }

  private function buildResponseDict() {
    if ($this->getError()) {
      $content = array(
        'error'             => $this->getError(),
        'error_description' => $this->getErrorDescription(),
      );
      $this->setContent($content);
    }

    $content = $this->getContent();
    if (!$content) {
      return '';
    }
    if ($this->getState()) {
      $content['state'] = $this->getState();
    }
    return $content;
  }

  public function buildResponseString() {
    return $this->encodeJSONForHTTPResponse($this->buildResponseDict());
  }

}
