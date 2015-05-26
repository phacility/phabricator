<?php

/**
 * Configuration source which proxies some other configuration source.
 */
abstract class PhabricatorConfigProxySource
  extends PhabricatorConfigSource {

  private $source;

  final protected function getSource() {
    if (!$this->source) {
      throw new Exception(pht('No configuration source set!'));
    }
    return $this->source;
  }

  final protected function setSource(PhabricatorConfigSource $source) {
    $this->source = $source;
    return $this;
  }

  public function getAllKeys() {
    return $this->getSource()->getAllKeys();
  }

  public function getKeys(array $keys) {
    return $this->getSource()->getKeys($keys);
  }

  public function canWrite() {
    return $this->getSource()->canWrite();
  }

  public function setKeys(array $keys) {
    $this->getSource()->setKeys($keys);
    return $this;
  }

  public function deleteKeys(array $keys) {
    $this->getSource()->deleteKeys($keys);
    return $this;
  }

  public function setName($name) {
    $this->getSource()->setName($name);
    return $this;
  }

  public function getName() {
    return $this->getSource()->getName();
  }

}
