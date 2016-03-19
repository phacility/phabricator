<?php

final class PhabricatorIteratorFileUploadSource
  extends PhabricatorFileUploadSource {

  private $iterator;

  public function setIterator(Iterator $iterator) {
    $this->iterator = $iterator;
    return $this;
  }

  public function getIterator() {
    return $this->iterator;
  }

  protected function newDataIterator() {
    return $this->getIterator();
  }

  protected function getDataLength() {
    return null;
  }

}
