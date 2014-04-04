<?php

final class DiffusionMercurialResponse extends AphrontResponse {

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
      array('Content-Type', 'application/mercurial-0.1'),
    );
    return array_merge(parent::getHeaders(), $headers);
  }

  public function getCacheHeaders() {
    return array();
  }

  public function getHTTPResponseCode() {
    return 200;
  }

}
