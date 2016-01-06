<?php

abstract class DiffusionBlameQuery extends DiffusionQuery {

  private $timeout;
  private $paths;

  public function setTimeout($timeout) {
    $this->timeout = $timeout;
    return $this;
  }

  public function getTimeout() {
    return $this->timeout;
  }

  public function setPaths(array $paths) {
    $this->paths = $paths;
    return $this;
  }

  public function getPaths() {
    return $this->paths;
  }

  abstract protected function newBlameFuture(DiffusionRequest $request, $path);

  abstract protected function resolveBlameFuture(ExecFuture $future);

  final public static function newFromDiffusionRequest(
    DiffusionRequest $request) {
    return parent::newQueryObject(__CLASS__, $request);
  }

  final protected function executeQuery() {
    $paths = $this->getPaths();
    $request = $this->getRequest();
    $timeout = $this->getTimeout();

    $futures = array();
    foreach ($paths as $path) {
      $future = $this->newBlameFuture($request, $path);

      if ($timeout) {
        $future->setTimeout($timeout);
      }

      $futures[$path] = $future;
    }


    $blame = array();

    if ($futures) {
      $futures = id(new FutureIterator($futures))
        ->limit(4);

      foreach ($futures as $path => $future) {
        $path_blame = $this->resolveBlameFuture($future);
        if ($path_blame !== null) {
          $blame[$path] = $path_blame;
        }
      }
    }

    return $blame;
  }

}
