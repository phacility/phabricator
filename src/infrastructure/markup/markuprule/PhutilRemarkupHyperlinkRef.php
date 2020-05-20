<?php

final class PhutilRemarkupHyperlinkRef
  extends Phobject {

  private $token;
  private $uri;
  private $embed;
  private $result;

  public function __construct(array $map) {
    $this->token = $map['token'];
    $this->uri = $map['uri'];
    $this->embed = ($map['mode'] === '{');
  }

  public function getToken() {
    return $this->token;
  }

  public function getURI() {
    return $this->uri;
  }

  public function isEmbed() {
    return $this->embed;
  }

  public function setResult($result) {
    $this->result = $result;
    return $this;
  }

  public function getResult() {
    return $this->result;
  }

}
