<?php

final class PhutilKeyValueCacheNamespace extends PhutilKeyValueCacheProxy {

  private $namespace;

  public function setNamespace($namespace) {
    if (strpos($namespace, ':') !== false) {
      throw new Exception(pht("Namespace can't contain colons."));
    }

    $this->namespace = $namespace.':';

    return $this;
  }

  public function setKeys(array $keys, $ttl = null) {
    return parent::setKeys(array_combine(
      $this->prefixKeys(array_keys($keys)),
      $keys), $ttl);
  }

  public function getKeys(array $keys) {
    $results = parent::getKeys($this->prefixKeys($keys));

    if (!$results) {
      return array();
    }

    return array_combine(
      $this->unprefixKeys(array_keys($results)),
      $results);
  }

  public function deleteKeys(array $keys) {
    return parent::deleteKeys($this->prefixKeys($keys));
  }

  private function prefixKeys(array $keys) {
    if ($this->namespace == null) {
      throw new Exception(pht('Namespace not set.'));
    }

    $prefixed_keys = array();
    foreach ($keys as $key) {
      $prefixed_keys[] = $this->namespace.$key;
    }

    return $prefixed_keys;
  }

  private function unprefixKeys(array $keys) {
    if ($this->namespace == null) {
      throw new Exception(pht('Namespace not set.'));
    }

    $unprefixed_keys = array();
    foreach ($keys as $key) {
      $unprefixed_keys[] = substr($key, strlen($this->namespace));
    }

    return $unprefixed_keys;
  }

}
