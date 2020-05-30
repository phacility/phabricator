<?php

/**
 * @task  memcache Managing Memcache
 */
final class PhutilMemcacheKeyValueCache extends PhutilKeyValueCache {

  private $servers = array();
  private $connections = array();


/* -(  Key-Value Cache Implementation  )------------------------------------- */


  public function isAvailable() {
    return function_exists('memcache_pconnect');
  }

  public function getKeys(array $keys) {
    $buckets = $this->bucketKeys($keys);
    $results = array();

    foreach ($buckets as $bucket => $bucket_keys) {
      $conn = $this->getConnection($bucket);
      $result = $conn->get($bucket_keys);
      if (!$result) {
        // If the call fails, treat it as a miss on all keys.
        $result = array();
      }

      $results += $result;
    }

    return $results;
  }

  public function setKeys(array $keys, $ttl = null) {
    $buckets = $this->bucketKeys(array_keys($keys));

    // Memcache interprets TTLs as:
    //
    //   - Seconds from now, for values from 1 to 2592000 (30 days).
    //   - Epoch timestamp, for values larger than 2592000.
    //
    // We support only relative TTLs, so convert excessively large relative
    // TTLs into epoch TTLs.
    if ($ttl > 2592000) {
      $effective_ttl = time() + $ttl;
    } else {
      $effective_ttl = $ttl;
    }

    foreach ($buckets as $bucket => $bucket_keys) {
      $conn = $this->getConnection($bucket);

      foreach ($bucket_keys as $key) {
        $conn->set($key, $keys[$key], 0, $effective_ttl);
      }
    }

    return $this;
  }

  public function deleteKeys(array $keys) {
    $buckets = $this->bucketKeys($keys);

    foreach ($buckets as $bucket => $bucket_keys) {
      $conn = $this->getConnection($bucket);
      foreach ($bucket_keys as $key) {
        $conn->delete($key);
      }
    }

    return $this;
  }

  public function destroyCache() {
    foreach ($this->servers as $key => $spec) {
      $this->getConnection($key)->flush();
    }
    return $this;
  }


/* -(  Managing Memcache  )-------------------------------------------------- */


  /**
   * Set available memcache servers. For example:
   *
   *   $cache->setServers(
   *     array(
   *       array(
   *         'host' => '10.0.0.20',
   *         'port' => 11211,
   *       ),
   *       array(
   *         'host' => '10.0.0.21',
   *         'port' => 11211,
   *       ),
   *    ));
   *
   * @param   list<dict>  List of server specifications.
   * @return  this
   * @task memcache
   */
  public function setServers(array $servers) {
    $this->servers = array_values($servers);
    return $this;
  }

  private function bucketKeys(array $keys) {
    $buckets = array();
    $n = count($this->servers);

    if (!$n) {
      throw new PhutilInvalidStateException('setServers');
    }

    foreach ($keys as $key) {
      $bucket = (int)((crc32($key) & 0x7FFFFFFF) % $n);
      $buckets[$bucket][] = $key;
    }

    return $buckets;
  }


  /**
   * @phutil-external-symbol function memcache_pconnect
   */
  private function getConnection($server) {
    if (empty($this->connections[$server])) {
      $spec = $this->servers[$server];
      $host = $spec['host'];
      $port = $spec['port'];

      $conn = memcache_pconnect($host, $spec['port']);

      if (!$conn) {
        throw new Exception(
          pht(
            'Unable to connect to memcache server (%s:%d)!',
            $host,
            $port));
      }

      $this->connections[$server] = $conn;
    }
    return $this->connections[$server];
  }

}
