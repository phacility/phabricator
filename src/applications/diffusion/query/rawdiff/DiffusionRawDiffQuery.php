<?php

abstract class DiffusionRawDiffQuery extends DiffusionQuery {

  private $timeout;
  private $linesOfContext = 65535;
  private $anchorCommit;
  private $againstCommit;
  private $byteLimit;

  final public static function newFromDiffusionRequest(
    DiffusionRequest $request) {
    return parent::newQueryObject(__CLASS__, $request);
  }

  final public function loadRawDiff() {
    return $this->executeQuery();
  }

  final public function setTimeout($timeout) {
    $this->timeout = $timeout;
    return $this;
  }

  final public function getTimeout() {
    return $this->timeout;
  }

  public function setByteLimit($byte_limit) {
    $this->byteLimit = $byte_limit;
    return $this;
  }

  public function getByteLimit() {
    return $this->byteLimit;
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

  protected function configureFuture(ExecFuture $future) {
    if ($this->getTimeout()) {
      $future->setTimeout($this->getTimeout());
    }

    if ($this->getByteLimit()) {
      $future->setStdoutSizeLimit($this->getByteLimit());
      $future->setStderrSizeLimit($this->getByteLimit());
    }
  }

}
