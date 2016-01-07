<?php

/**
 * NOTE: this class should only be used where local access to the repository
 * is guaranteed and NOT from within the Diffusion application. Diffusion
 * should use Conduit method 'diffusion.filecontentquery' to get this sort
 * of data.
 */
abstract class DiffusionFileContentQuery extends DiffusionQuery {

  private $fileContent;
  private $viewer;
  private $timeout;
  private $byteLimit;

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

  public function setViewer(PhabricatorUser $user) {
    $this->viewer = $user;
    return $this;
  }

  public function getViewer() {
    return $this->viewer;
  }

  final public static function newFromDiffusionRequest(
    DiffusionRequest $request) {
    return parent::newQueryObject(__CLASS__, $request);
  }

  abstract public function getFileContentFuture();
  abstract protected function executeQueryFromFuture(Future $future);

  final public function loadFileContentFromFuture(Future $future) {

    if ($this->timeout) {
      $future->setTimeout($this->timeout);
    }

    if ($this->getByteLimit()) {
      $future->setStdoutSizeLimit($this->getByteLimit());
    }

    try {
      $file_content = $this->executeQueryFromFuture($future);
    } catch (CommandException $ex) {
      if (!$future->getWasKilledByTimeout()) {
        throw $ex;
      }

      $message = pht(
        '<Attempt to load this file was terminated after %s second(s).>',
        $this->timeout);

      $file_content = new DiffusionFileContent();
      $file_content->setCorpus($message);
    }

    $this->fileContent = $file_content;

    $repository = $this->getRequest()->getRepository();
    $try_encoding = $repository->getDetail('encoding');
    if ($try_encoding) {
        $this->fileContent->setCorpus(
          phutil_utf8_convert(
            $this->fileContent->getCorpus(), 'UTF-8', $try_encoding));
    }

    return $this->fileContent;
  }

  final protected function executeQuery() {
    return $this->loadFileContentFromFuture($this->getFileContentFuture());
  }

  final public function loadFileContent() {
    return $this->executeQuery();
  }

  final public function getRawData() {
    return $this->fileContent->getCorpus();
  }

}
