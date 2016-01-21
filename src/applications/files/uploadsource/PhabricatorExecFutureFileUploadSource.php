<?php

final class PhabricatorExecFutureFileUploadSource
  extends PhabricatorFileUploadSource {

  private $future;

  public function setExecFuture(ExecFuture $future) {
    $this->future = $future;
    return $this;
  }

  public function getExecFuture() {
    return $this->future;
  }

  protected function newDataIterator() {
    $future = $this->getExecFuture();

    return id(new LinesOfALargeExecFuture($future))
      ->setDelimiter(null);
  }

  protected function getDataLength() {
    return null;
  }

}
