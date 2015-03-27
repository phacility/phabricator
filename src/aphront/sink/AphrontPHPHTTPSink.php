<?php

/**
 * Concrete HTTP sink which uses "echo" and "header()" to emit data.
 */
final class AphrontPHPHTTPSink extends AphrontHTTPSink {

  protected function emitHTTPStatus($code, $message = '') {
    if ($code != 200) {
      $header = "HTTP/1.0 {$code}";
      if (strlen($message)) {
        $header .= " {$message}";
      }
      header($header);
    }
  }

  protected function emitHeader($name, $value) {
    header("{$name}: {$value}", $replace = false);
  }

  protected function emitData($data) {
    echo $data;

    // NOTE: We don't call flush() here because it breaks HTTPS under Apache.
    // See T7620 for discussion. Even without an explicit flush, PHP appears to
    // have reasonable behavior here: the echo will block if internal buffers
    // are full, and data will be sent to the client once enough of it has
    // been buffered.
  }

  protected function isWritable() {
    return !connection_aborted();
  }

}
