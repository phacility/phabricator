<?php

/**
 * Proxies another cache and serializes values.
 *
 * This allows more complex data to be stored in a cache which can only store
 * strings.
 */
final class PhabricatorKeyValueSerializingCacheProxy
  extends PhutilKeyValueCacheProxy {

  public function getKeys(array $keys) {
    $results = parent::getKeys($keys);

    $reads = array();
    foreach ($results as $key => $result) {
      $structure = @unserialize($result);

      // The unserialize() function returns false when unserializing a
      // literal `false`, and also when it fails. If we get a literal
      // `false`, test if the serialized form is the same as the
      // serialization of `false` and miss the cache otherwise.
      if ($structure === false) {
        static $serialized_false;
        if ($serialized_false === null) {
          $serialized_false = serialize(false);
        }
        if ($result !== $serialized_false) {
          continue;
        }
      }

      $reads[$key] = $structure;
    }

    return $reads;
  }

  public function setKeys(array $keys, $ttl = null) {
    $writes = array();
    foreach ($keys as $key => $value) {
      if (is_object($value)) {
        throw new Exception(
          pht(
            'Serializing cache can not write objects (for key "%s")!',
            $key));
      }
      $writes[$key] = serialize($value);
    }

    return parent::setKeys($writes, $ttl);
  }


}
