<?php

/**
 * Abstract class which wraps some sort of output mechanism for HTTP responses.
 * Normally this is just @{class:AphrontPHPHTTPSink}, which uses "echo" and
 * "header()" to emit responses.
 *
 * Mostly, this class allows us to do install security or metrics hooks in the
 * output pipeline.
 *
 * @task write  Writing Response Components
 * @task emit   Emitting the Response
 */
abstract class AphrontHTTPSink {


/* -(  Writing Response Components  )---------------------------------------- */


  /**
   * Write an HTTP status code to the output.
   *
   * @param int Numeric HTTP status code.
   * @return void
   */
  final public function writeHTTPStatus($code, $message = '') {
    if (!preg_match('/^\d{3}$/', $code)) {
      throw new Exception("Malformed HTTP status code '{$code}'!");
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
        throw new Exception('Malformed header.');
      }
      list($name, $value) = $header;

      if (strpos($name, ':') !== false) {
        throw new Exception(
          'Declining to emit response with malformed HTTP header name: '.
          $name);
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
          "Declining to emit response with unsafe HTTP header: ".
          "<'".$name."', '".$value."'>.");
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
    // Build the content iterator first, in case it throws. Ideally, we'd
    // prefer to handle exceptions before we emit the response status or any
    // HTTP headers.
    $data = $response->getContentIterator();

    $all_headers = array_merge(
      $response->getHeaders(),
      $response->getCacheHeaders());

    $this->writeHTTPStatus(
      $response->getHTTPResponseCode(),
      $response->getHTTPResponseMessage());
    $this->writeHeaders($all_headers);

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
