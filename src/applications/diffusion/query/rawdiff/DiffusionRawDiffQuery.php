<?php

abstract class DiffusionRawDiffQuery
  extends DiffusionFileFutureQuery {

  private $linesOfContext = 65535;
  private $anchorCommit;
  private $againstCommit;

  final public static function newFromDiffusionRequest(
    DiffusionRequest $request) {
    return parent::newQueryObject(__CLASS__, $request);
  }

  final public function setLinesOfContext($lines_of_context) {
    $this->linesOfContext = $lines_of_context;
    return $this;
  }

  final public function getLinesOfContext() {
    return $this->linesOfContext;
  }

  final public function setAgainstCommit($value) {
    $this->againstCommit = $value;
    return $this;
  }

  final public function getAgainstCommit() {
    return $this->againstCommit;
  }

  public function setAnchorCommit($anchor_commit) {
    $this->anchorCommit = $anchor_commit;
    return $this;
  }

  public function getAnchorCommit() {
    if ($this->anchorCommit) {
      return $this->anchorCommit;
    }

    return $this->getRequest()->getStableCommit();
  }

}
