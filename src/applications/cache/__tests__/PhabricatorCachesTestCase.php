<?php

final class PhabricatorCachesTestCase
  extends PhabricatorTestCase {

  public function testRequestCache() {
    $cache = PhabricatorCaches::getRequestCache();

    $test_key = 'unit.'.Filesystem::readRandomCharacters(8);

    $default_value = pht('Default');
    $new_value = pht('New Value');

    $this->assertEqual(
      $default_value,
      $cache->getKey($test_key, $default_value));

    // Set a key, verify it persists.
    $cache = PhabricatorCaches::getRequestCache();
    $cache->setKey($test_key, $new_value);
    $this->assertEqual(
      $new_value,
      $cache->getKey($test_key, $default_value));

    // Refetch the cache, verify it's really a cache.
    $cache = PhabricatorCaches::getRequestCache();
    $this->assertEqual(
      $new_value,
      $cache->getKey($test_key, $default_value));

    // Destroy the cache.
    PhabricatorCaches::destroyRequestCache();

    // Now, the value should be missing again.
    $cache = PhabricatorCaches::getRequestCache();
    $this->assertEqual(
      $default_value,
      $cache->getKey($test_key, $default_value));
  }

}
