<?php

abstract class PhutilKeyValueCacheProxy extends PhutilKeyValueCache {

  private $proxy;

  final public function __construct(PhutilKeyValueCache $proxy) {
    $this->proxy = $proxy;
  }

  final protected function getProxy() {
    return $this->proxy;
  }

  public function isAvailable() {
    return $this->getProxy()->isAvailable();
  }


  public function getKeys(array $keys) {
    return $this->getProxy()->getKeys($keys);
  }


  public function setKeys(array $keys, $ttl = null) {
    return $this->getProxy()->setKeys($keys, $ttl);
  }


  public function deleteKeys(array $keys) {
    return $this->getProxy()->deleteKeys($keys);
  }


  public function destroyCache() {
    return $this->getProxy()->destroyCache();
  }

  public function __call($method, array $arguments) {
    return call_user_func_array(
      array($this->getProxy(), $method),
      $arguments);
  }

}
