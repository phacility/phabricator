<?php

/**
 * @group aphront
 */
final class AphrontPlainTextResponse extends AphrontResponse {

  public function setContent($content) {
    $this->content = $content;
    return $this;
  }

  public function buildResponseString() {
    return $this->content;
  }

  public function getHeaders() {
    $headers = array(
      array('Content-Type', 'text/plain'),
    );

    return array_merge(parent::getHeaders(), $headers);
  }

}
