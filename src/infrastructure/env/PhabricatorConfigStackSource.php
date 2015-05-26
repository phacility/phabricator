<?php

/**
 * Configuration source which reads from a stack of other configuration
 * sources.
 *
 * This source is writable if any source in the stack is writable. Writes happen
 * to the first writable source only.
 */
final class PhabricatorConfigStackSource
  extends PhabricatorConfigSource {

  private $stack = array();

  public function pushSource(PhabricatorConfigSource $source) {
    array_unshift($this->stack, $source);
    return $this;
  }

  public function popSource() {
    if (empty($this->stack)) {
      throw new Exception(pht('Popping an empty %s!', __CLASS__));
    }
    return array_shift($this->stack);
  }

  public function getStack() {
    return $this->stack;
  }

  public function getKeys(array $keys) {
    $result = array();
    foreach ($this->stack as $source) {
      $result = $result + $source->getKeys($keys);
    }
    return $result;
  }

  public function getAllKeys() {
    $result = array();
    foreach ($this->stack as $source) {
      $result = $result + $source->getAllKeys();
    }
    return $result;
  }

  public function canWrite() {
    foreach ($this->stack as $source) {
      if ($source->canWrite()) {
        return true;
      }
    }
    return false;
  }

  public function setKeys(array $keys) {
    foreach ($this->stack as $source) {
      if ($source->canWrite()) {
        $source->setKeys($keys);
        return;
      }
    }

    // We can't write; this will throw an appropriate exception.
    parent::setKeys($keys);
  }

  public function deleteKeys(array $keys) {
    foreach ($this->stack as $source) {
      if ($source->canWrite()) {
        $source->deleteKeys($keys);
        return;
      }
    }

    // We can't write; this will throw an appropriate exception.
    parent::deleteKeys($keys);
  }

}
