<?php

abstract class DiffusionFileContentQuery extends DiffusionQuery {

  private $timeout;
  private $byteLimit;

  private $didHitByteLimit = false;
  private $didHitTimeLimit = false;

  public function setTimeout($timeout) {
    $this->timeout = $timeout;
    return $this;
  }

  public function getTimeout() {
    return $this->timeout;
  }

  public function setByteLimit($byte_limit) {
    $this->byteLimit = $byte_limit;
    return $this;
  }

  public function getByteLimit() {
    return $this->byteLimit;
  }

  final public static function newFromDiffusionRequest(
    DiffusionRequest $request) {
    return parent::newQueryObject(__CLASS__, $request);
  }

  final public function getExceededByteLimit() {
    return $this->didHitByteLimit;
  }

  final public function getExceededTimeLimit() {
    return $this->didHitTimeLimit;
  }

  abstract protected function getFileContentFuture();
  abstract protected function resolveFileContentFuture(Future $future);

  final protected function executeQuery() {
    $future = $this->getFileContentFuture();

    if ($this->getTimeout()) {
      $future->setTimeout($this->getTimeout());
    }

    $byte_limit = $this->getByteLimit();
    if ($byte_limit) {
      $future->setStdoutSizeLimit($byte_limit + 1);
    }

    try {
      $file_content = $this->resolveFileContentFuture($future);
    } catch (CommandException $ex) {
      if (!$future->getWasKilledByTimeout()) {
        throw $ex;
      }

      $this->didHitTimeLimit = true;
      $file_content = null;
    }

    if ($byte_limit && (strlen($file_content) > $byte_limit)) {
      $this->didHitByteLimit = true;
      $file_content = null;
    }

    return $file_content;
  }

}
