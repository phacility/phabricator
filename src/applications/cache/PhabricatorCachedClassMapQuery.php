<?php

/**
 * Cached @{class:PhutilClassMapQuery} which can perform lookups for single
 * classes efficiently.
 *
 * Some class trees (like Conduit methods and PHID types) contain a huge number
 * of classes but are frequently accessed by looking for a specific class by
 * a known identifier (like a Conduit method name or a PHID type constant).
 *
 * Loading the entire class map for these cases has a small but measurable
 * performance cost. Instead, we can build a cache from each Conduit method
 * name to just the class required to serve that request. This means that we
 * load fewer classes and have less overhead to execute API calls.
 */
final class PhabricatorCachedClassMapQuery
  extends Phobject {

  private $query;
  private $queryCacheKey;
  private $mapKeyMethod;
  private $objectMap;

  public function setClassMapQuery(PhutilClassMapQuery $query) {
    $this->query = $query;
    return $this;
  }

  public function setMapKeyMethod($method) {
    $this->mapKeyMethod = $method;
    return $this;
  }

  public function loadClasses(array $values) {
    $cache = PhabricatorCaches::getRuntimeCache();

    $cache_keys = $this->getCacheKeys($values);
    $cache_map = $cache->getKeys($cache_keys);

    $results = array();
    $writes = array();
    foreach ($cache_keys as $value => $cache_key) {
      if (isset($cache_map[$cache_key])) {
        $class_name = $cache_map[$cache_key];
        try {
          $result = $this->newObject($class_name);
          if ($this->getObjectMapKey($result) === $value) {
            $results[$value] = $result;
            continue;
          }
        } catch (Exception $ex) {
          // Keep going, we'll handle this immediately below.
        }

        // If we didn't "continue;" above, there was either a direct issue with
        // the cache or the cached class did not generate the correct map key.
        // Wipe the cache and pretend we missed.
        $cache->deleteKey($cache_key);
      }

      if ($this->objectMap === null) {
        $this->objectMap = $this->newObjectMap();
      }

      if (isset($this->objectMap[$value])) {
        $results[$value] = $this->objectMap[$value];
        $writes[$cache_key] = get_class($results[$value]);
      }
    }

    if ($writes) {
      $cache->setKeys($writes);
    }

    return $results;
  }

  public function loadClass($value) {
    $result = $this->loadClasses(array($value));
    return idx($result, $value);
  }

  private function getCacheKeys(array $values) {
    if ($this->queryCacheKey === null) {
      $this->queryCacheKey = $this->query->getCacheKey();
    }

    $key = $this->queryCacheKey;
    $method = $this->mapKeyMethod;

    $keys = array();
    foreach ($values as $value) {
      $keys[$value] = "classmap({$key}).{$method}({$value})";
    }

    return $keys;
  }

  private function newObject($class_name) {
    return newv($class_name, array());
  }

  private function newObjectMap() {
    $map = $this->query->execute();

    $result = array();
    foreach ($map as $object) {
      $value = $this->getObjectMapKey($object);
      if (isset($result[$value])) {
        $other = $result[$value];
        throw new Exception(
          pht(
            'Two objects (of classes "%s" and "%s") generate the same map '.
            'value ("%s"). Each object must generate a unique map value.',
            get_class($object),
            get_class($other),
            $value));
      }
      $result[$value] = $object;
    }

    return $result;
  }

  private function getObjectMapKey($object) {
    return call_user_func(array($object, $this->mapKeyMethod));
  }

}
