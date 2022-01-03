<?php

final class HarbormasterMessageException extends Exception {

  private $title;
  private $body = array();

  public function __construct($title, $body = null) {
    $this->setTitle($title);
    $this->appendParagraph($body);

    parent::__construct(
      pht(
        '%s: %s',
        $title,
        $body));
  }

  public function setTitle($title) {
    $this->title = $title;
    return $this;
  }

  public function getTitle() {
    return $this->title;
  }

  public function appendParagraph($description) {
    $this->body[] = $description;
    return $this;
  }

  public function getBody() {
    return $this->body;
  }

  public function newDisplayString() {
    $title = $this->getTitle();

    $body = $this->getBody();
    $body = implode("\n\n", $body);

    return pht('%s: %s', $title, $body);
  }

}
