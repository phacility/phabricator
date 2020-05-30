<?php

final class PhabricatorDiffInlineCommentContext
  extends PhabricatorInlineCommentContext {

  private $filename;
  private $headLines;
  private $bodyLines;
  private $tailLines;

  public static function newFromCacheData(array $map) {
    $context = new self();

    $context->setFilename(idx($map, 'filename'));
    $context->setHeadLines(idx($map, 'headLines'));
    $context->setBodyLines(idx($map, 'bodyLines'));
    $context->setTailLines(idx($map, 'tailLines'));

    return $context;
  }

  public function newCacheDataMap() {
    return array(
      'filename' => $this->getFilename(),
      'headLines' => $this->getHeadLines(),
      'bodyLines' => $this->getBodyLines(),
      'tailLines' => $this->getTailLines(),
    );
  }

  public function setFilename($filename) {
    $this->filename = $filename;
    return $this;
  }

  public function getFilename() {
    return $this->filename;
  }

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
