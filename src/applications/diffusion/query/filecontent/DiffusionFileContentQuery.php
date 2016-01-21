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

    $drequest = $this->getRequest();

    $name = basename($drequest->getPath());
    $ttl = PhabricatorTime::getNow() + phutil_units('48 hours in seconds');

    try {
      $threshold = PhabricatorFileStorageEngine::getChunkThreshold();
      $future->setReadBufferSize($threshold);

      $source = id(new PhabricatorExecFutureFileUploadSource())
        ->setName($name)
        ->setTTL($ttl)
        ->setViewPolicy(PhabricatorPolicies::POLICY_NOONE)
        ->setExecFuture($future);

      $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
        $file = $source->uploadFile();
      unset($unguarded);

    } catch (CommandException $ex) {
      if (!$future->getWasKilledByTimeout()) {
        throw $ex;
      }

      $this->didHitTimeLimit = true;
      $file = null;
    }

    if ($byte_limit && ($file->getByteSize() > $byte_limit)) {
      $this->didHitByteLimit = true;

      $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
        id(new PhabricatorDestructionEngine())
          ->destroyObject($file);
      unset($unguarded);

      $file = null;
    }

    return $file;
  }

}
