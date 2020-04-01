<?php

/**
 * Key-value cache implemented in the current request. All storage is local
 * to this request (i.e., the current page) and destroyed after the request
 * exits. This means the first request to this cache for a given key on a page
 * will ALWAYS miss.
 *
 * This cache exists mostly to support unit tests. In a well-designed
 * applications, you generally should not be fetching the same data over and
 * over again in one request, so this cache should be of limited utility.
 * If using this cache improves application performance, especially if it
 * improves it significantly, it may indicate an architectural problem in your
 * application.
 */
final class PhutilInRequestKeyValueCache extends PhutilKeyValueCache {

  private $cache = array();
  private $ttl = array();
  private $limit = 0;


  /**
   * Set a limit on the number of keys this cache may contain.
   *
   * When too many keys are inserted, the oldest keys are removed from the
   * cache. Setting a limit of `0` disables the cache.
   *
   * @param int Maximum number of items to store in the cache.
   * @return this
   */
  public function setLimit($limit) {
    $this->limit = $limit;
    return $this;
  }


/* -(  Key-Value Cache Implementation  )------------------------------------- */


  public function isAvailable() {
    return true;
  }

  public function getKeys(array $keys) {
    $results = array();
    $now = time();
    foreach ($keys as $key) {
      if (!isset($this->cache[$key]) && !array_key_exists($key, $this->cache)) {
        continue;
      }
      if (isset($this->ttl[$key]) && ($this->ttl[$key] < $now)) {
        continue;
      }
      $results[$key] = $this->cache[$key];
    }

    return $results;
  }

  public function setKeys(array $keys, $ttl = null) {

    foreach ($keys as $key => $value) {
      $this->cache[$key] = $value;
    }

    if ($ttl) {
      $end = time() + $ttl;
      foreach ($keys as $key => $value) {
        $this->ttl[$key] = $end;
      }
    } else {
      foreach ($keys as $key => $value) {
        unset($this->ttl[$key]);
      }
    }

    if ($this->limit) {
      $count = count($this->cache);
      if ($count > $this->limit) {
        $remove = array();
        foreach ($this->cache as $key => $value) {
          $remove[] = $key;

          $count--;
          if ($count <= $this->limit) {
            break;
          }
        }

        $this->deleteKeys($remove);
      }
    }

    return $this;
  }

  public function deleteKeys(array $keys) {
    foreach ($keys as $key) {
      unset($this->cache[$key]);
      unset($this->ttl[$key]);
    }

    return $this;
  }

  public function getAllKeys() {
    return $this->cache;
  }

  public function destroyCache() {
    $this->cache = array();
    $this->ttl = array();

    return $this;
  }

}
