<?php

final class HarbormasterExecFuture
  extends Future {

  private $future;
  private $stdout;
  private $stderr;

  public function setFuture(ExecFuture $future) {
    $this->future = $future;
    return $this;
  }

  public function getFuture() {
    return $this->future;
  }

  public function setLogs(
    HarbormasterBuildLog $stdout,
    HarbormasterBuildLog $stderr) {
    $this->stdout = $stdout;
    $this->stderr = $stderr;
    return $this;
  }

  public function isReady() {
    $future = $this->getFuture();

    $result = $future->isReady();

    list($stdout, $stderr) = $future->read();
    $future->discardBuffers();

    if ($this->stdout) {
      $this->stdout->append($stdout);
    }

    if ($this->stderr) {
      $this->stderr->append($stderr);
    }

    return $result;
  }

  protected function getResult() {
    return $this->getFuture()->getResult();
  }

}
