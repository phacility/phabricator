<?php

abstract class DiffusionRawDiffQuery extends DiffusionQuery {

  private $request;
  private $timeout;
  private $linesOfContext = 65535;
  private $againstCommit;

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

}
