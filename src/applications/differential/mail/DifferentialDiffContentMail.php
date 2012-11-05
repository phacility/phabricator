<?php

final class DifferentialDiffContentMail extends DifferentialMail {

  protected $content;

  public function __construct(DifferentialRevision $revision, $content) {
    $this->setRevision($revision);
    $this->content = $content;
  }

  protected function renderVaryPrefix() {
    return '[Content]';
  }

  protected function renderBody() {
    return $this->content;
  }
}
