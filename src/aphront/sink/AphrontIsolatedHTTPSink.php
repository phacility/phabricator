<?php

/**
 * Isolated HTTP sink for testing.
 *
 * @group aphront
 */
final class AphrontIsolatedHTTPSink extends AphrontHTTPSink {

  private $status;
  private $headers;
  private $data;

  protected function emitHTTPStatus($code) {
    $this->status = $code;
  }

  protected function emitHeader($name, $value) {
    $this->headers[] = array($name, $value);
  }

  protected function emitData($data) {
    $this->data .= $data;
  }

  public function getEmittedHTTPStatus() {
    return $this->status;
  }

  public function getEmittedHeaders() {
    return $this->headers;
  }

  public function getEmittedData() {
    return $this->data;
  }

}
