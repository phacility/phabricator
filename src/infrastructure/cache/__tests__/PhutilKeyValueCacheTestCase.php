<?php

final class PhutilKeyValueCacheTestCase extends PhutilTestCase {

  public function testInRequestCache() {
    $cache = new PhutilInRequestKeyValueCache();
    $this->doCacheTest($cache);
    $cache->destroyCache();
  }

  public function testInRequestCacheLimit() {
    $cache = new PhutilInRequestKeyValueCache();
    $cache->setLimit(4);

    $cache->setKey(1, 1);
    $cache->setKey(2, 2);
    $cache->setKey(3, 3);
    $cache->setKey(4, 4);

    $this->assertEqual(
      array(
        1 => 1,
        2 => 2,
        3 => 3,
        4 => 4,
      ),
      $cache->getAllKeys());


    $cache->setKey(5, 5);

    $this->assertEqual(
      array(
        2 => 2,
        3 => 3,
        4 => 4,
        5 => 5,
      ),
      $cache->getAllKeys());
  }

  public function testOnDiskCache() {
    $cache = new PhutilOnDiskKeyValueCache();
    $cache->setCacheFile(new TempFile());
    $this->doCacheTest($cache);
    $cache->destroyCache();
  }

  public function testAPCCache() {
    $cache = new PhutilAPCKeyValueCache();
    if (!$cache->isAvailable()) {
      $this->assertSkipped(pht('Cache not available.'));
    }
    $this->doCacheTest($cache);
  }

  public function testDirectoryCache() {
    $cache = new PhutilDirectoryKeyValueCache();

    $dir = Filesystem::createTemporaryDirectory();
    $cache->setCacheDirectory($dir);
    $this->doCacheTest($cache);
    $cache->destroyCache();
  }

  public function testDirectoryCacheSpecialDirectoryRules() {
    $cache = new PhutilDirectoryKeyValueCache();

    $dir = Filesystem::createTemporaryDirectory();
    $dir = $dir.'/dircache/';
    $cache->setCacheDirectory($dir);

    $cache->setKey('a', 1);
    $this->assertEqual(true, Filesystem::pathExists($dir.'/a.cache'));

    $cache->setKey('a/b', 1);
    $this->assertEqual(true, Filesystem::pathExists($dir.'/a/'));
    $this->assertEqual(true, Filesystem::pathExists($dir.'/a/b.cache'));

    $cache->deleteKey('a/b');
    $this->assertEqual(false, Filesystem::pathExists($dir.'/a/'));
    $this->assertEqual(false, Filesystem::pathExists($dir.'/a/b.cache'));

    $cache->destroyCache();
    $this->assertEqual(false, Filesystem::pathExists($dir));
  }

  public function testNamespaceCache() {
    $namespace = 'namespace'.mt_rand();
    $in_request_cache = new PhutilInRequestKeyValueCache();
    $cache = new PhutilKeyValueCacheNamespace($in_request_cache);
    $cache->setNamespace($namespace);

    $test_info = get_class($cache);
    $keys = array(
      'key1' => mt_rand(),
      'key2' => '',
      'key3' => 'Phabricator',
    );
    $cache->setKeys($keys);
    $cached_keys = $in_request_cache->getAllKeys();

    foreach ($keys as $key => $value) {
      $cached_key = $namespace.':'.$key;

      $this->assertTrue(
        isset($cached_keys[$cached_key]),
        $test_info);

      $this->assertEqual(
        $value,
        $cached_keys[$cached_key],
        $test_info);
    }

    $cache->destroyCache();

    $this->doCacheTest($cache);
    $cache->destroyCache();
  }

  public function testCacheStack() {
    $req_cache = new PhutilInRequestKeyValueCache();
    $disk_cache = new PhutilOnDiskKeyValueCache();
    $disk_cache->setCacheFile(new TempFile());
    $apc_cache = new PhutilAPCKeyValueCache();

    $stack = array(
      $req_cache,
      $disk_cache,
    );

    if ($apc_cache->isAvailable()) {
      $stack[] = $apc_cache;
    }

    $cache = new PhutilKeyValueCacheStack();
    $cache->setCaches($stack);

    $this->doCacheTest($cache);

    $disk_cache->destroyCache();
    $req_cache->destroyCache();
  }

  private function doCacheTest(PhutilKeyValueCache $cache) {
    $key1 = 'test:'.mt_rand();
    $key2 = 'test:'.mt_rand();

    $default = 'cache-miss';
    $value1  = 'cache-hit1';
    $value2  = 'cache-hit2';

    $test_info = get_class($cache);

    // Test that we miss correctly on missing values.

    $this->assertEqual(
      $default,
      $cache->getKey($key1, $default),
      $test_info);
    $this->assertEqual(
      array(
      ),
      $cache->getKeys(array($key1, $key2)),
      $test_info);


    // Test that we can set individual keys.

    $cache->setKey($key1, $value1);
    $this->assertEqual(
      $value1,
      $cache->getKey($key1, $default),
      $test_info);
    $this->assertEqual(
      array(
        $key1 => $value1,
      ),
      $cache->getKeys(array($key1, $key2)),
      $test_info);


    // Test that we can delete individual keys.

    $cache->deleteKey($key1);

    $this->assertEqual(
      $default,
      $cache->getKey($key1, $default),
      $test_info);
    $this->assertEqual(
      array(
      ),
      $cache->getKeys(array($key1, $key2)),
      $test_info);



    // Test that we can set multiple keys.

    $cache->setKeys(
      array(
        $key1 => $value1,
        $key2 => $value2,
      ));

    $this->assertEqual(
      $value1,
      $cache->getKey($key1, $default),
      $test_info);
    $this->assertEqual(
      array(
        $key1 => $value1,
        $key2 => $value2,
      ),
      $cache->getKeys(array($key1, $key2)),
      $test_info);


    // Test that we can delete multiple keys.

    $cache->deleteKeys(array($key1, $key2));

    $this->assertEqual(
      $default,
      $cache->getKey($key1, $default),
      $test_info);
    $this->assertEqual(
      array(
      ),
      $cache->getKeys(array($key1, $key2)),
      $test_info);


    // NOTE: The TTL tests are necessarily slow (we must sleep() through the
    // TTLs) and do not work with APC (it does not TTL until the next request)
    // so they're disabled by default. If you're developing the cache stack,
    // it may be useful to run them.

    return;

    // Test that keys expire when they TTL.

    $cache->setKey($key1, $value1, 1);
    $cache->setKey($key2, $value2, 5);

    $this->assertEqual($value1, $cache->getKey($key1, $default));
    $this->assertEqual($value2, $cache->getKey($key2, $default));

    sleep(2);

    $this->assertEqual($default, $cache->getKey($key1, $default));
    $this->assertEqual($value2, $cache->getKey($key2, $default));


    // Test that setting a 0 TTL overwrites a nonzero TTL.

    $cache->setKey($key1, $value1, 1);
    $this->assertEqual($value1, $cache->getKey($key1, $default));
    $cache->setKey($key1, $value1, 0);
    $this->assertEqual($value1, $cache->getKey($key1, $default));
    sleep(2);
    $this->assertEqual($value1, $cache->getKey($key1, $default));
  }

}
