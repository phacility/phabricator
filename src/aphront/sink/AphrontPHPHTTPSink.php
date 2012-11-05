<?php

/**
 * Concrete HTTP sink which uses "echo" and "header()" to emit data.
 *
 * @group aphront
 */
final class AphrontPHPHTTPSink extends AphrontHTTPSink {

  protected function emitHTTPStatus($code) {
    if ($code != 200) {
      header("HTTP/1.0 {$code}");
    }
  }

  protected function emitHeader($name, $value) {
    header("{$name}: {$value}", $replace = false);
  }

  protected function emitData($data) {
    echo $data;
  }

}
