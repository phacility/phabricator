<?php

final class AphrontWebpageResponse extends AphrontHTMLResponse {

  private $content;

  public function setContent($content) {
    $this->content = $content;
    return $this;
  }

  public function buildResponseString() {
    return hsprintf('%s', $this->content);
  }

}
