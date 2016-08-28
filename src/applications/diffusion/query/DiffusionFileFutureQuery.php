<?php

abstract class DiffusionFileFutureQuery
  extends DiffusionQuery {

  private $timeout;
  private $byteLimit;

  private $didHitByteLimit = false;
  private $didHitTimeLimit = false;

  public static function getConduitParameters() {
    return array(
      'timeout' => 'optional int',
      'byteLimit' => 'optional int',
    );
  }

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

  final public function getExceededByteLimit() {
    return $this->didHitByteLimit;
  }

  final public function getExceededTimeLimit() {
    return $this->didHitTimeLimit;
  }

  abstract protected function newQueryFuture();

  final public function respondToConduitRequest(ConduitAPIRequest $request) {
    $drequest = $this->getRequest();

    $timeout = $request->getValue('timeout');
    if ($timeout) {
      $this->setTimeout($timeout);
    }

    $byte_limit = $request->getValue('byteLimit');
    if ($byte_limit) {
      $this->setByteLimit($byte_limit);
    }

    $file = $this->execute();

    $too_slow = (bool)$this->getExceededTimeLimit();
    $too_huge = (bool)$this->getExceededByteLimit();

    $file_phid = null;
    if (!$too_slow && !$too_huge) {
      $repository = $drequest->getRepository();
      $repository_phid = $repository->getPHID();

      $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
        $file->attachToObject($repository_phid);
      unset($unguarded);

      $file_phid = $file->getPHID();
    }

    return array(
      'tooSlow' => $too_slow,
      'tooHuge' => $too_huge,
      'filePHID' => $file_phid,
    );
  }

  final public function executeInline() {
    $future = $this->newConfiguredQueryFuture();
    list($stdout) = $future->resolvex();
    return $stdout;
  }

  final protected function executeQuery() {
    $future = $this->newQueryFuture();

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

    $byte_limit = $this->getByteLimit();

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

  private function newConfiguredQueryFuture() {
    $future = $this->newQueryFuture();

    if ($this->getTimeout()) {
      $future->setTimeout($this->getTimeout());
    }

    $byte_limit = $this->getByteLimit();
    if ($byte_limit) {
      $future->setStdoutSizeLimit($byte_limit + 1);
    }

    return $future;
  }

}
