<?php

/**
 * Stacks multiple caches on top of each other, with readthrough semantics:
 *
 *   - For reads, we try each cache in order until we find all the keys.
 *   - For writes, we set the keys in each cache.
 *
 * @task  config    Configuring the Stack
 */
final class PhutilKeyValueCacheStack extends PhutilKeyValueCache {


  /**
   * Forward list of caches in the stack (from the nearest cache to the farthest
   * cache).
   */
  private $cachesForward;


  /**
   * Backward list of caches in the stack (from the farthest cache to the
   * nearest cache).
   */
  private $cachesBackward;


  /**
   * TTL to use for any writes which are side effects of the next read
   * operation.
   */
  private $nextTTL;


/* -(  Configuring the Stack  )---------------------------------------------- */


  /**
   * Set the caches which comprise this stack.
   *
   * @param   list<PhutilKeyValueCache> Ordered list of key-value caches.
   * @return  this
   * @task    config
   */
  public function setCaches(array $caches) {
    assert_instances_of($caches, 'PhutilKeyValueCache');
    $this->cachesForward  = $caches;
    $this->cachesBackward = array_reverse($caches);

    return $this;
  }


  /**
   * Set the readthrough TTL for the next cache operation. The TTL applies to
   * any keys set by the next call to @{method:getKey} or @{method:getKeys},
   * and is reset after the call finishes.
   *
   *   // If this causes any caches to fill, they'll fill with a 15-second TTL.
   *   $stack->setNextTTL(15)->getKey('porcupine');
   *
   *   // TTL does not persist; this will use no TTL.
   *   $stack->getKey('hedgehog');
   *
   * @param   int TTL in seconds.
   * @return  this
   *
   * @task    config
   */
  public function setNextTTL($ttl) {
    $this->nextTTL = $ttl;
    return $this;
  }


/* -(  Key-Value Cache Implementation  )------------------------------------- */


  public function getKeys(array $keys) {

    $remaining = array_fuse($keys);
    $results = array();
    $missed = array();

    try {
      foreach ($this->cachesForward as $cache) {
        $result = $cache->getKeys($remaining);
        $remaining = array_diff_key($remaining, $result);
        $results += $result;
        if (!$remaining) {
          while ($cache = array_pop($missed)) {
            // TODO: This sets too many results in the closer caches, although
            // it probably isn't a big deal in most cases; normally we're just
            // filling the request cache.
            $cache->setKeys($result, $this->nextTTL);
          }
          break;
        }
        $missed[] = $cache;
      }
      $this->nextTTL = null;
    } catch (Exception $ex) {
      $this->nextTTL = null;
      throw $ex;
    }

    return $results;
  }


  public function setKeys(array $keys, $ttl = null) {
    foreach ($this->cachesBackward as $cache) {
      $cache->setKeys($keys, $ttl);
    }
  }


  public function deleteKeys(array $keys) {
    foreach ($this->cachesBackward as $cache) {
      $cache->deleteKeys($keys);
    }
  }


  public function destroyCache() {
    foreach ($this->cachesBackward as $cache) {
      $cache->destroyCache();
    }
  }

}
