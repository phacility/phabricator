<?php


class EmailDiffLine {
  public int $lineNumber;
  /** one of "added", "removed" or "no-change */
  public string $type;
  public string $rawContent;

  public function __construct(int $lineNumber, string $type, string $rawContent) {
    $this->lineNumber = $lineNumber;
    $this->type = $type;
    $this->rawContent = $rawContent;
  }
}