<?php

/**
 * TODO: Should be final, but isn't because of Aphront403Response / 404Response.
 *
 * @group aphront
 */
class AphrontWebpageResponse extends AphrontResponse {

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
      array('Content-Type', 'text/html; charset=UTF-8'),
    );
    $headers = array_merge(parent::getHeaders(), $headers);
    return $headers;
  }

}
