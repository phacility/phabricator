<?php

final class PhabricatorPasteSnippet extends Phobject {

  const FULL = 'full';
  const FIRST_LINES = 'first_lines';
  const FIRST_BYTES = 'first_bytes';

  private $content;
  private $type;

  public function __construct($content, $type) {
    $this->content = $content;
    $this->type = $type;
  }

  public function getContent() {
    return $this->content;
  }

  public function getType() {
    return $this->type;
  }
}
