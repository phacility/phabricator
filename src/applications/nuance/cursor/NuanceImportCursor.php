<?php

abstract class NuanceImportCursor extends Phobject {

  private $cursorData;
  private $cursorKey;
  private $source;
  private $viewer;

  abstract protected function shouldPullDataFromSource();
  abstract protected function pullDataFromSource();

  final public function getCursorType() {
    return $this->getPhobjectClassConstant('CURSORTYPE', 32);
  }

  public function setCursorData(NuanceImportCursorData $cursor_data) {
    $this->cursorData = $cursor_data;
    return $this;
  }

  public function getCursorData() {
    return $this->cursorData;
  }

  public function setSource($source) {
    $this->source = $source;
    return $this;
  }

  public function getSource() {
    return $this->source;
  }

  public function setCursorKey($cursor_key) {
    $this->cursorKey = $cursor_key;
    return $this;
  }

  public function getCursorKey() {
    return $this->cursorKey;
  }

  public function setViewer($viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function getViewer() {
    return $this->viewer;
  }

  final public function importFromSource() {
    if (!$this->shouldPullDataFromSource()) {
      return false;
    }

    $source = $this->getSource();
    $key = $this->getCursorKey();

    $parts = array(
      'nsc',
      $source->getID(),
      PhabricatorHash::digestToLength($key, 20),
    );
    $lock_name = implode('.', $parts);

    $lock = PhabricatorGlobalLock::newLock($lock_name);
    $lock->lock(1);

    try {
      $more_data = $this->pullDataFromSource();
    } catch (Exception $ex) {
      $lock->unlock();
      throw $ex;
    }

    $lock->unlock();

    return $more_data;
  }

  final public function newEmptyCursorData(NuanceSource $source) {
    return id(new NuanceImportCursorData())
      ->setCursorKey($this->getCursorKey())
      ->setCursorType($this->getCursorType())
      ->setSourcePHID($source->getPHID());
  }

  final protected function logInfo($message) {
    echo tsprintf(
      "<cursor:%s> %s\n",
      $this->getCursorKey(),
      $message);

    return $this;
  }

  final protected function getCursorProperty($key, $default = null) {
    return $this->getCursorData()->getCursorProperty($key, $default);
  }

  final protected function setCursorProperty($key, $value) {
    $this->getCursorData()->setCursorProperty($key, $value);
    return $this;
  }

}
