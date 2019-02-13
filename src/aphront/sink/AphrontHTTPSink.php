<?php

/**
 * Abstract class which wraps some sort of output mechanism for HTTP responses.
 * Normally this is just @{class:AphrontPHPHTTPSink}, which uses "echo" and
 * "header()" to emit responses.
 *
 * @task write  Writing Response Components
 * @task emit   Emitting the Response
 */
abstract class AphrontHTTPSink extends Phobject {

  private $showStackTraces = false;

  final public function setShowStackTraces($show_stack_traces) {
    $this->showStackTraces = $show_stack_traces;
    return $this;
  }

  final public function getShowStackTraces() {
    return $this->showStackTraces;
  }


/* -(  Writing Response Components  )---------------------------------------- */


  /**
   * Write an HTTP status code to the output.
   *
   * @param int Numeric HTTP status code.
   * @return void
   */
  final public function writeHTTPStatus($code, $message = '') {
    if (!preg_match('/^\d{3}$/', $code)) {
      throw new Exception(pht("Malformed HTTP status code '%s'!", $code));
    }

    $code = (int)$code;
    $this->emitHTTPStatus($code, $message);
  }


  /**
   * Write HTTP headers to the output.
   *
   * @param list<pair> List of <name, value> pairs.
   * @return void
   */
  final public function writeHeaders(array $headers) {
    foreach ($headers as $header) {
      if (!is_array($header) || count($header) !== 2) {
        throw new Exception(pht('Malformed header.'));
      }
      list($name, $value) = $header;

      if (strpos($name, ':') !== false) {
        throw new Exception(
          pht(
            'Declining to emit response with malformed HTTP header name: %s',
            $name));
      }

      // Attackers may perform an "HTTP response splitting" attack by making
      // the application emit certain types of headers containing newlines:
      //
      //   http://en.wikipedia.org/wiki/HTTP_response_splitting
      //
      // PHP has built-in protections against HTTP response-splitting, but they
      // are of dubious trustworthiness:
      //
      //   http://news.php.net/php.internals/57655

      if (preg_match('/[\r\n\0]/', $name.$value)) {
        throw new Exception(
          pht(
            'Declining to emit response with unsafe HTTP header: %s',
            "<'".$name."', '".$value."'>."));
      }
    }

    foreach ($headers as $header) {
      list($name, $value) = $header;
      $this->emitHeader($name, $value);
    }
  }


  /**
   * Write HTTP body data to the output.
   *
   * @param string Body data.
   * @return void
   */
  final public function writeData($data) {
    $this->emitData($data);
  }


  /**
   * Write an entire @{class:AphrontResponse} to the output.
   *
   * @param AphrontResponse The response object to write.
   * @return void
   */
  final public function writeResponse(AphrontResponse $response) {
    $response->willBeginWrite();

    // Build the content iterator first, in case it throws. Ideally, we'd
    // prefer to handle exceptions before we emit the response status or any
    // HTTP headers.
    $data = $response->getContentIterator();

    // This isn't an exceptionally clean separation of concerns, but we need
    // to add CSP headers for all response types (including both web pages
    // and dialogs) and can't determine the correct CSP until after we render
    // the page (because page elements like Recaptcha may add CSP rules).
    $static = CelerityAPI::getStaticResourceResponse();
    foreach ($static->getContentSecurityPolicyURIMap() as $kind => $uris) {
      foreach ($uris as $uri) {
        $response->addContentSecurityPolicyURI($kind, $uri);
      }
    }

    $all_headers = array_merge(
      $response->getHeaders(),
      $response->getCacheHeaders());

    $this->writeHTTPStatus(
      $response->getHTTPResponseCode(),
      $response->getHTTPResponseMessage());
    $this->writeHeaders($all_headers);

    // Allow clients an unlimited amount of time to download the response.

    // This allows clients to perform a "slow loris" attack, where they
    // download a large response very slowly to tie up process slots. However,
    // concurrent connection limits and "RequestReadTimeout" already prevent
    // this attack. We could add our own minimum download rate here if we want
    // to make this easier to configure eventually.

    // For normal page responses, we've fully rendered the page into a string
    // already so all that's left is writing it to the client.

    // For unusual responses (like large file downloads) we may still be doing
    // some meaningful work, but in theory that work is intrinsic to streaming
    // the response.

    set_time_limit(0);

    $abort = false;
    foreach ($data as $block) {
      if (!$this->isWritable()) {
        $abort = true;
        break;
      }
      $this->writeData($block);
    }

    $response->didCompleteWrite($abort);
  }


/* -(  Emitting the Response  )---------------------------------------------- */


  abstract protected function emitHTTPStatus($code, $message = '');
  abstract protected function emitHeader($name, $value);
  abstract protected function emitData($data);
  abstract protected function isWritable();

}
