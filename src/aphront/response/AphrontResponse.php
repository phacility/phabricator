<?php

/**
 * @group aphront
 */
abstract class AphrontResponse {

  private $request;
  private $cacheable = false;
  private $responseCode = 200;
  private $lastModified = null;

  protected $frameable;

  public function setRequest($request) {
    $this->request = $request;
    return $this;
  }

  public function getRequest() {
    return $this->request;
  }

  public function getHeaders() {
    $headers = array();
    if (!$this->frameable) {
      $headers[] = array('X-Frame-Options', 'Deny');
    }

    return $headers;
  }

  public function setCacheDurationInSeconds($duration) {
    $this->cacheable = $duration;
    return $this;
  }

  public function setLastModified($epoch_timestamp) {
    $this->lastModified = $epoch_timestamp;
    return $this;
  }

  public function setHTTPResponseCode($code) {
    $this->responseCode = $code;
    return $this;
  }

  public function getHTTPResponseCode() {
    return $this->responseCode;
  }

  public function setFrameable($frameable) {
    $this->frameable = $frameable;
    return $this;
  }

  public static function processValueForJSONEncoding(&$value, $key) {
    if ($value instanceof PhutilSafeHTMLProducerInterface) {
      // This renders the producer down to PhutilSafeHTML, which will then
      // be simplified into a string below.
      $value = hsprintf('%s', $value);
    }

    if ($value instanceof PhutilSafeHTML) {
      // TODO: Javelin supports implicity conversion of '__html' objects to
      // JX.HTML, but only for Ajax responses, not behaviors. Just leave things
      // as they are for now (where behaviors treat responses as HTML or plain
      // text at their discretion).
      $value = $value->getHTMLContent();
    }
  }

  public static function encodeJSONForHTTPResponse(array $object) {

    array_walk_recursive(
      $object,
      array('AphrontResponse', 'processValueForJSONEncoding'));

    $response = json_encode($object);

    // Prevent content sniffing attacks by encoding "<" and ">", so browsers
    // won't try to execute the document as HTML even if they ignore
    // Content-Type and X-Content-Type-Options. See T865.
    $response = str_replace(
      array('<', '>'),
      array('\u003c', '\u003e'),
      $response);

    return $response;
  }

  protected function addJSONShield($json_response) {

    // Add a shield to prevent "JSON Hijacking" attacks where an attacker
    // requests a JSON response using a normal <script /> tag and then uses
    // Object.prototype.__defineSetter__() or similar to read response data.
    // This header causes the browser to loop infinitely instead of handing over
    // sensitive data.

    $shield = 'for (;;);';

    $response = $shield.$json_response;

    return $response;
  }

  public function getCacheHeaders() {
    $headers = array();
    if ($this->cacheable) {
      $headers[] = array(
        'Expires',
        $this->formatEpochTimestampForHTTPHeader(time() + $this->cacheable));
    } else {
      $headers[] = array(
        'Cache-Control',
        'private, no-cache, no-store, must-revalidate');
      $headers[] = array(
        'Pragma',
        'no-cache');
      $headers[] = array(
        'Expires',
        'Sat, 01 Jan 2000 00:00:00 GMT');
    }

    if ($this->lastModified) {
      $headers[] = array(
        'Last-Modified',
        $this->formatEpochTimestampForHTTPHeader($this->lastModified));
    }

    // IE has a feature where it may override an explicit Content-Type
    // declaration by inferring a content type. This can be a security risk
    // and we always explicitly transmit the correct Content-Type header, so
    // prevent IE from using inferred content types. This only offers protection
    // on recent versions of IE; IE6/7 and Opera currently ignore this header.
    $headers[] = array('X-Content-Type-Options', 'nosniff');

    return $headers;
  }

  private function formatEpochTimestampForHTTPHeader($epoch_timestamp) {
    return gmdate('D, d M Y H:i:s', $epoch_timestamp).' GMT';
  }

  abstract public function buildResponseString();

}
