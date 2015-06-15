<?php

final class AphrontPlainTextResponse extends AphrontResponse {

  private $content;

  public function setContent($content) {
    $this->content = $content;
    return $this;
  }

  public function buildResponseString() {
    return $this->content;
  }

  public function getHeaders() {
    $headers = array(
      array('Content-Type', 'text/plain; charset=utf-8'),
    );

    return array_merge(parent::getHeaders(), $headers);
  }

}
