<?php

final class PhabricatorPeopleMailEngineException
  extends Exception {

  private $title;
  private $body;

  public function __construct($title, $body) {
    $this->title = $title;
    $this->body = $body;

    parent::__construct(pht('%s: %s', $title, $body));
  }

  public function getTitle() {
    return $this->title;
  }

  public function getBody() {
    return $this->body;
  }

}
