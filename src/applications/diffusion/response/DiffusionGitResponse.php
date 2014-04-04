<?php

final class DiffusionGitResponse extends AphrontResponse {

  private $httpCode;
  private $headers = array();
  private $response;

  public function setGitData($data) {
    list($headers, $body) = explode("\r\n\r\n", $data, 2);
    $this->response = $body;
    $headers = explode("\r\n", $headers);

    $matches = null;
    $this->httpCode = 200;
    $this->headers = array();
    foreach ($headers as $header) {
      if (preg_match('/^Status:\s*(\d+)/i', $header, $matches)) {
        $this->httpCode = (int)$matches[1];
      } else {
        $this->headers[] = explode(': ', $header, 2);
      }
    }

    return $this;
  }

  public function buildResponseString() {
    return $this->response;
  }

  public function getHeaders() {
    return array_merge(parent::getHeaders(), $this->headers);
  }

  public function getCacheHeaders() {
    return array();
  }

  public function getHTTPResponseCode() {
    return $this->httpCode;
  }

}
