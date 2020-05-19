<?php

final class PhabricatorDiffInlineCommentContext
  extends PhabricatorInlineCommentContext {

  private $headLines;
  private $bodyLines;
  private $tailLines;

  public function setHeadLines(array $head_lines) {
    $this->headLines = $head_lines;
    return $this;
  }

  public function getHeadLines() {
    return $this->headLines;
  }

  public function setBodyLines(array $body_lines) {
    $this->bodyLines = $body_lines;
    return $this;
  }

  public function getBodyLines() {
    return $this->bodyLines;
  }

  public function setTailLines(array $tail_lines) {
    $this->tailLines = $tail_lines;
    return $this;
  }

  public function getTailLines() {
    return $this->tailLines;
  }

}
