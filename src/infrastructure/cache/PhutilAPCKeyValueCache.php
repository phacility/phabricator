<?php

/**
 * Interface to the APC key-value cache. This is a very high-performance cache
 * which is local to the current machine.
 */
final class PhutilAPCKeyValueCache extends PhutilKeyValueCache {


/* -(  Key-Value Cache Implementation  )------------------------------------- */


  public function isAvailable() {
    return (function_exists('apc_fetch') || function_exists('apcu_fetch')) &&
           ini_get('apc.enabled') &&
           (ini_get('apc.enable_cli') || php_sapi_name() != 'cli');
  }

  public function getKeys(array $keys, $ttl = null) {
    static $is_apcu;
    if ($is_apcu === null) {
      $is_apcu = self::isAPCu();
    }

    $results = array();
    $fetched = false;
    foreach ($keys as $key) {
      if ($is_apcu) {
        $result = apcu_fetch($key, $fetched);
      } else {
        $result = apc_fetch($key, $fetched);
      }

      if ($fetched) {
        $results[$key] = $result;
      }
    }
    return $results;
  }

  public function setKeys(array $keys, $ttl = null) {
    static $is_apcu;
    if ($is_apcu === null) {
      $is_apcu = self::isAPCu();
    }

    // NOTE: Although modern APC supports passing an array to `apc_store()`,
    // it is not supported by older version of APC or by HPHP.

    foreach ($keys as $key => $value) {
      if ($is_apcu) {
        apcu_store($key, $value, $ttl);
      } else {
        apc_store($key, $value, $ttl);
      }
    }

    return $this;
  }

  public function deleteKeys(array $keys) {
    static $is_apcu;
    if ($is_apcu === null) {
      $is_apcu = self::isAPCu();
    }

    foreach ($keys as $key) {
      if ($is_apcu) {
        apcu_delete($key);
      } else {
        apc_delete($key);
      }
    }

    return $this;
  }

  public function destroyCache() {
    static $is_apcu;
    if ($is_apcu === null) {
      $is_apcu = self::isAPCu();
    }

    if ($is_apcu) {
      apcu_clear_cache();
    } else {
      apc_clear_cache('user');
    }

    return $this;
  }

  private static function isAPCu() {
    return function_exists('apcu_fetch');
  }

}
