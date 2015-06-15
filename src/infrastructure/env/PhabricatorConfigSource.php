<?php

abstract class PhabricatorConfigSource extends Phobject {

  private $name;

  public function setName($name) {
    $this->name = $name;
    return $this;
  }

  public function getName() {
    return $this->name;
  }

  abstract public function getKeys(array $keys);
  abstract public function getAllKeys();

  public function canWrite() {
    return false;
  }

  public function setKeys(array $keys) {
    throw new Exception(
      pht('This configuration source does not support writes.'));
  }

  public function deleteKeys(array $keys) {
    throw new Exception(
      pht('This configuration source does not support writes.'));
  }

}
