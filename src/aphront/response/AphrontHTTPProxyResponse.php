<?php

/**
 * Responds to a request by proxying an HTTP future.
 *
 * NOTE: This is currently very inefficient for large responses, and buffers
 * the entire response into memory before returning it. It should be updated
 * to stream the response instead, but we need to complete additional
 * infrastructure work first.
 */
final class AphrontHTTPProxyResponse extends AphrontResponse {

  private $future;
  private $headers;
  private $httpCode;

  public function setHTTPFuture(HTTPSFuture $future) {
    $this->future = $future;
    return $this;
  }

  public function getHTTPFuture() {
    return $this->future;
  }

  public function getCacheHeaders() {
    return array();
  }

  public function getHeaders() {
    $this->readRequestHeaders();
    return array_merge(
      parent::getHeaders(),
      $this->headers,
      array(
        array('X-Phabricator-Proxy', 'true'),
      ));
  }

  public function buildResponseString() {
    // TODO: AphrontResponse needs to support streaming responses.
    return $this->readRequest();
  }

  public function getHTTPResponseCode() {
    $this->readRequestHeaders();
    return $this->httpCode;
  }

  private function readRequestHeaders() {
    // TODO: This should read only the headers.
    $this->readRequest();
  }

  private function readRequest() {
    // TODO: This is grossly inefficient for large requests.

    list($status, $body, $headers) = $this->future->resolve();
    $this->httpCode = $status->getStatusCode();

    // Strip "Transfer-Encoding" headers. Particularly, the server we proxied
    // may have chunked the response, but cURL will already have un-chunked it.
    // If we emit the header and unchunked data, the response becomes invalid.
    foreach ($headers as $key => $header) {
      list($header_head, $header_body) = $header;
      $header_head = phutil_utf8_strtolower($header_head);
      switch ($header_head) {
        case 'transfer-encoding':
          unset($headers[$key]);
          break;
      }
    }

    $this->headers = $headers;

    return $body;
  }

}
