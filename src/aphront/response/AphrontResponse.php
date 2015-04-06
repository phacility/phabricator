<?php

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


/* -(  Content  )------------------------------------------------------------ */


  public function getContentIterator() {
    return array($this->buildResponseString());
  }

  public function buildResponseString() {
    throw new PhutilMethodNotImplementedException();
  }


/* -(  Metadata  )----------------------------------------------------------- */


  public function getHeaders() {
    $headers = array();
    if (!$this->frameable) {
      $headers[] = array('X-Frame-Options', 'Deny');
    }

    if ($this->getRequest() && $this->getRequest()->isHTTPS()) {
      $hsts_key = 'security.strict-transport-security';
      $use_hsts = PhabricatorEnv::getEnvConfig($hsts_key);
      if ($use_hsts) {
        $duration = phutil_units('365 days in seconds');
      } else {
        // If HSTS has been disabled, tell browsers to turn it off. This may
        // not be effective because we can only disable it over a valid HTTPS
        // connection, but it best represents the configured intent.
        $duration = 0;
      }

      $headers[] = array(
        'Strict-Transport-Security',
        "max-age={$duration}; includeSubdomains; preload",
      );
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

  public function getHTTPResponseMessage() {
    switch ($this->getHTTPResponseCode()) {
      case 100: return 'Continue';
      case 101: return 'Switching Protocols';
      case 200: return 'OK';
      case 201: return 'Created';
      case 202: return 'Accepted';
      case 203: return 'Non-Authoritative Information';
      case 204: return 'No Content';
      case 205: return 'Reset Content';
      case 206: return 'Partial Content';
      case 300: return 'Multiple Choices';
      case 301: return 'Moved Permanently';
      case 302: return 'Found';
      case 303: return 'See Other';
      case 304: return 'Not Modified';
      case 305: return 'Use Proxy';
      case 306: return 'Switch Proxy';
      case 307: return 'Temporary Redirect';
      case 400: return 'Bad Request';
      case 401: return 'Unauthorized';
      case 402: return 'Payment Required';
      case 403: return 'Forbidden';
      case 404: return 'Not Found';
      case 405: return 'Method Not Allowed';
      case 406: return 'Not Acceptable';
      case 407: return 'Proxy Authentication Required';
      case 408: return 'Request Timeout';
      case 409: return 'Conflict';
      case 410: return 'Gone';
      case 411: return 'Length Required';
      case 412: return 'Precondition Failed';
      case 413: return 'Request Entity Too Large';
      case 414: return 'Request-URI Too Long';
      case 415: return 'Unsupported Media Type';
      case 416: return 'Requested Range Not Satisfiable';
      case 417: return 'Expectation Failed';
      case 418: return "I'm a teapot";
      case 426: return 'Upgrade Required';
      case 500: return 'Internal Server Error';
      case 501: return 'Not Implemented';
      case 502: return 'Bad Gateway';
      case 503: return 'Service Unavailable';
      case 504: return 'Gateway Timeout';
      case 505: return 'HTTP Version Not Supported';
      default:  return '';
    }
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
        $this->formatEpochTimestampForHTTPHeader(time() + $this->cacheable),
      );
    } else {
      $headers[] = array(
        'Cache-Control',
        'private, no-cache, no-store, must-revalidate',
      );
      $headers[] = array(
        'Pragma',
        'no-cache',
      );
      $headers[] = array(
        'Expires',
        'Sat, 01 Jan 2000 00:00:00 GMT',
      );
    }

    if ($this->lastModified) {
      $headers[] = array(
        'Last-Modified',
        $this->formatEpochTimestampForHTTPHeader($this->lastModified),
      );
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

  public function didCompleteWrite($aborted) {
    return;
  }

}
