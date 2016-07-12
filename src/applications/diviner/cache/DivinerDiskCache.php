<?php

abstract class DivinerDiskCache extends Phobject {

  private $cache;

  public function __construct($cache_directory, $name) {
    $dir_cache = id(new PhutilDirectoryKeyValueCache())
      ->setCacheDirectory($cache_directory);
    $profiled_cache = id(new PhutilKeyValueCacheProfiler($dir_cache))
      ->setProfiler(PhutilServiceProfiler::getInstance())
      ->setName($name);
    $this->cache = $profiled_cache;
  }

  protected function getCache() {
    return $this->cache;
  }

  public function delete() {
    $this->getCache()->destroyCache();
    return $this;
  }

  /**
   * Convert a long-form hash key like `ccbbaaaaaaaaaaaaaaaaaaaaaaaaaaaaN` into
   * a shortened directory form, like `cc/bb/aaaaaaaaN`. In conjunction with
   * @{class:PhutilDirectoryKeyValueCache}, this gives us nice directories
   * inside `.divinercache` instead of a million hash files with huge names at
   * the top level.
   */
  protected function getHashKey($hash) {
    return implode(
      '/',
      array(
        substr($hash, 0, 2),
        substr($hash, 2, 2),
        substr($hash, 4, 8),
      ));
  }

}
