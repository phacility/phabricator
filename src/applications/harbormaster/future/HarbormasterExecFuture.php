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
    if ($this->hasResult()) {
      return true;
    }

    $future = $this->getFuture();

    $is_ready = $future->isReady();

    list($stdout, $stderr) = $future->read();
    $future->discardBuffers();

    if ($this->stdout) {
      $this->stdout->append($stdout);
    }

    if ($this->stderr) {
      $this->stderr->append($stderr);
    }

    if ($future->hasResult()) {
      $this->setResult($future->getResult());
    }

    // TODO: This should probably be implemented as a FutureProxy; it will
    // not currently propagate exceptions or sockets properly.

    return $is_ready;
  }

}
