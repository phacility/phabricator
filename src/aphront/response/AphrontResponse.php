<?php

abstract class AphrontResponse extends Phobject {

  private $request;
  private $cacheable = false;
  private $canCDN;
  private $responseCode = 200;
  private $lastModified = null;
  private $contentSecurityPolicyURIs;
  private $disableContentSecurityPolicy;
  protected $frameable;
  private $headers = array();

  public function setRequest($request) {
    $this->request = $request;
    return $this;
  }

  public function getRequest() {
    return $this->request;
  }

  final public function addContentSecurityPolicyURI($kind, $uri) {
    if ($this->contentSecurityPolicyURIs === null) {
      $this->contentSecurityPolicyURIs = array(
         'script-src' => array(),
         'connect-src' => array(),
         'frame-src' => array(),
         'form-action' => array(),
         'object-src' => array(),
       );
    }

    if (!isset($this->contentSecurityPolicyURIs[$kind])) {
      throw new Exception(
        pht(
          'Unknown Content-Security-Policy URI kind "%s".',
          $kind));
    }

    $this->contentSecurityPolicyURIs[$kind][] = (string)$uri;

    return $this;
  }

  final public function setDisableContentSecurityPolicy($disable) {
    $this->disableContentSecurityPolicy = $disable;
    return $this;
  }

  final public function addHeader($key, $value) {
    $this->headers[] = array($key, $value);
    return $this;
  }


/* -(  Content  )------------------------------------------------------------ */


  public function getContentIterator() {
    // By default, make sure responses are truly returning a string, not some
    // kind of object that behaves like a string.

    // We're going to remove the execution time limit before dumping the
    // response into the sink, and want any rendering that's going to occur
    // to happen BEFORE we release the limit.

    return array(
      (string)$this->buildResponseString(),
    );
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

    $csp = $this->newContentSecurityPolicyHeader();
    if ($csp !== null) {
      $headers[] = array('Content-Security-Policy', $csp);
    }

    $headers[] = array('Referrer-Policy', 'no-referrer');

    foreach ($this->headers as $header) {
      $headers[] = $header;
    }

    return $headers;
  }

  private function newContentSecurityPolicyHeader() {
    if ($this->disableContentSecurityPolicy) {
      return null;
    }

    // NOTE: We may return a response during preflight checks (for example,
    // if a user has a bad version of PHP).

    // In this case, setup isn't complete yet and we can't access environmental
    // configuration. If we aren't able to read the environment, just decline
    // to emit a Content-Security-Policy header.

    try {
      $cdn = PhabricatorEnv::getEnvConfig('security.alternate-file-domain');
      $base_uri = PhabricatorEnv::getURI('/');
    } catch (Exception $ex) {
      return null;
    }

    $csp = array();
    if ($cdn) {
      $default = $this->newContentSecurityPolicySource($cdn);
    } else {
      // If an alternate file domain is not configured and the user is viewing
      // a Phame blog on a custom domain or some other custom site, we'll still
      // serve resources from the main site. Include the main site explicitly.
      $base_uri = $this->newContentSecurityPolicySource($base_uri);

      $default = "'self' {$base_uri}";
    }

    $csp[] = "default-src {$default}";

    // We use "data:" URIs to inline small images into CSS. This policy allows
    // "data:" URIs to be used anywhere, but there doesn't appear to be a way
    // to say that "data:" URIs are okay in CSS files but not in the document.
    $csp[] = "img-src {$default} data:";

    // We use inline style="..." attributes in various places, many of which
    // are legitimate. We also currently use a <style> tag to implement the
    // "Monospaced Font Preference" setting.
    $csp[] = "style-src {$default} 'unsafe-inline'";

    // On a small number of pages, including the Stripe workflow and the
    // ReCAPTCHA challenge, we embed external Javascript directly.
    $csp[] = $this->newContentSecurityPolicy('script-src', $default);

    // We need to specify that we can connect to ourself in order for AJAX
    // requests to work.
    $csp[] = $this->newContentSecurityPolicy('connect-src', "'self'");

    // DarkConsole and PHPAST both use frames to render some content.
    $csp[] = $this->newContentSecurityPolicy('frame-src', "'self'");

    // This is a more modern flavor of of "X-Frame-Options" and prevents
    // clickjacking attacks where the page is included in a tiny iframe and
    // the user is convinced to click a element on the page, which really
    // clicks a dangerous button hidden under a picture of a cat.
    if ($this->frameable) {
      $csp[] = "frame-ancestors 'self'";
    } else {
      $csp[] = "frame-ancestors 'none'";
    }

    // Block relics of the old world: Flash, Java applets, and so on. Note
    // that Chrome prevents the user from viewing PDF documents if they are
    // served with a policy which excludes the domain they are served from.
    $csp[] = $this->newContentSecurityPolicy('object-src', "'none'");

    // Don't allow forms to submit offsite.

    // This can result in some trickiness with file downloads if applications
    // try to start downloads by submitting a dialog. Redirect to the file's
    // download URI instead of submitting a form to it.
    $csp[] = $this->newContentSecurityPolicy('form-action', "'self'");

    // Block use of "<base>" to change the origin of relative URIs on the page.
    $csp[] = "base-uri 'none'";

    $csp = implode('; ', $csp);

    return $csp;
  }

  private function newContentSecurityPolicy($type, $defaults) {
    if ($defaults === null) {
      $sources = array();
    } else {
      $sources = (array)$defaults;
    }

    $uris = $this->contentSecurityPolicyURIs;
    if (isset($uris[$type])) {
      foreach ($uris[$type] as $uri) {
        $sources[] = $this->newContentSecurityPolicySource($uri);
      }
    }
    $sources = array_unique($sources);

    return $type.' '.implode(' ', $sources);
  }

  private function newContentSecurityPolicySource($uri) {
    // Some CSP URIs are ultimately user controlled (like notification server
    // URIs and CDN URIs) so attempt to stop an attacker from injecting an
    // unsafe source (like 'unsafe-eval') into the CSP header.

    $uri = id(new PhutilURI($uri))
      ->setPath(null)
      ->setFragment(null)
      ->removeAllQueryParams();

    $uri = (string)$uri;
    if (preg_match('/[ ;\']/', $uri)) {
      throw new Exception(
        pht(
          'Attempting to emit a response with an unsafe source ("%s") in the '.
          'Content-Security-Policy header.',
          $uri));
    }

    return $uri;
  }

  public function setCacheDurationInSeconds($duration) {
    $this->cacheable = $duration;
    return $this;
  }

  public function setCanCDN($can_cdn) {
    $this->canCDN = $can_cdn;
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
      array(__CLASS__, 'processValueForJSONEncoding'));

    $response = phutil_json_encode($object);

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
      $cache_control = array();
      $cache_control[] = sprintf('max-age=%d', $this->cacheable);

      if ($this->canCDN) {
        $cache_control[] = 'public';
      } else {
        $cache_control[] = 'private';
      }

      $headers[] = array(
        'Cache-Control',
        implode(', ', $cache_control),
      );

      $headers[] = array(
        'Expires',
        $this->formatEpochTimestampForHTTPHeader(time() + $this->cacheable),
      );
    } else {
      $headers[] = array(
        'Cache-Control',
        'no-store',
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

  protected function shouldCompressResponse() {
    return true;
  }

  public function willBeginWrite() {
    // If we've already sent headers, these "ini_set()" calls will warn that
    // they have no effect. Today, this always happens because we're inside
    // a unit test, so just skip adjusting the setting.

    if (!headers_sent()) {
      if ($this->shouldCompressResponse()) {
        // Enable automatic compression here. Webservers sometimes do this for
        // us, but we now detect the absence of compression and warn users about
        // it so try to cover our bases more thoroughly.
        ini_set('zlib.output_compression', 1);
      } else {
        ini_set('zlib.output_compression', 0);
      }
    }
  }

  public function didCompleteWrite($aborted) {
    return;
  }

}
