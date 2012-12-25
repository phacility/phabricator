<?php

final class PhabricatorConfigDictionarySource
  extends PhabricatorConfigSource {

  private $dictionary;

  public function __construct(array $dictionary) {
    $this->dictionary = $dictionary;
  }

  public function getAllKeys() {
    return $this->dictionary;
  }

  public function getKeys(array $keys) {
    return array_select_keys($this->dictionary, $keys);
  }

  public function canWrite() {
    return true;
  }

  public function setKeys(array $keys) {
    $this->dictionary = $keys + $this->dictionary;
    return $this;
  }

  public function deleteKeys(array $keys) {
    foreach ($keys as $key) {
      unset($this->dictionary[$key]);
    }
    return $keys;
  }

}
