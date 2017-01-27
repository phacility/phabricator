<?php

final class PhabricatorPasteSnippet extends Phobject {

  const FULL = 'full';
  const FIRST_LINES = 'first_lines';
  const FIRST_BYTES = 'first_bytes';

  private $content;
  private $type;
  private $contentLineCount;

  public function __construct($content, $type, $content_line_count) {
    $this->content = $content;
    $this->type = $type;
    $this->contentLineCount = $content_line_count;
  }

  public function getContent() {
    return $this->content;
  }

  public function getType() {
    return $this->type;
  }

  public function getContentLineCount() {
    return $this->contentLineCount;
  }
}
