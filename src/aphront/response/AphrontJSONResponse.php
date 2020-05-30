<?php

final class AphrontJSONResponse extends AphrontResponse {

  private $content;
  private $addJSONShield;

  public function setContent($content) {
    $this->content = $content;
    return $this;
  }

  public function setAddJSONShield($should_add) {
    $this->addJSONShield = $should_add;
    return $this;
  }

  public function shouldAddJSONShield() {
    if ($this->addJSONShield === null) {
      return true;
    }
    return (bool)$this->addJSONShield;
  }

  public function buildResponseString() {
    $response = $this->encodeJSONForHTTPResponse($this->content);
    if ($this->shouldAddJSONShield()) {
      $response = $this->addJSONShield($response);
    }
    return $response;
  }

  public function getHeaders() {
    $headers = parent::getHeaders();

    $headers[] = array('Content-Type', 'application/json');

    return $headers;
  }

}
