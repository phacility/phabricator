<?php

/**
 * Interface to a directory-based disk cache. Storage persists across requests.
 *
 * This cache is very very slow, and most suitable for command line scripts
 * which need to build large caches derived from sources like working copies
 * (for example, Diviner). This cache performs better for large amounts of
 * data than @{class:PhutilOnDiskKeyValueCache} because each key is serialized
 * individually, but this comes at the cost of having even slower reads and
 * writes.
 *
 * In addition to having slow reads and writes, this entire cache locks for
 * any read or write activity.
 *
 * Keys for this cache treat the character "/" specially, and encode it as
 * a new directory on disk. This can help keep the cache organized and keep the
 * number of items in any single directory under control, by using keys like
 * "ab/cd/efghijklmn".
 *
 * @task  kvimpl    Key-Value Cache Implementation
 * @task  storage   Cache Storage
 */
final class PhutilDirectoryKeyValueCache extends PhutilKeyValueCache {

  private $lock;
  private $cacheDirectory;


/* -(  Key-Value Cache Implementation  )------------------------------------- */


  public function isAvailable() {
    return true;
  }


  public function getKeys(array $keys) {
    $this->validateKeys($keys);

    try {
      $this->lockCache();
    } catch (PhutilLockException $ex) {
      return array();
    }

    $now = time();

    $results = array();
    foreach ($keys as $key) {
      $key_file = $this->getKeyFile($key);
      try {
        $data = Filesystem::readFile($key_file);
      } catch (FilesystemException $ex) {
        continue;
      }

      $data = unserialize($data);
      if (!$data) {
        continue;
      }

      if (isset($data['ttl']) && $data['ttl'] < $now) {
        continue;
      }

      $results[$key] = $data['value'];
    }

    $this->unlockCache();

    return $results;
  }


  public function setKeys(array $keys, $ttl = null) {
    $this->validateKeys(array_keys($keys));

    $this->lockCache(15);

    if ($ttl) {
      $ttl_epoch = time() + $ttl;
    } else {
      $ttl_epoch = null;
    }

    foreach ($keys as $key => $value) {
      $dict = array(
        'value' => $value,
      );
      if ($ttl_epoch) {
        $dict['ttl'] = $ttl_epoch;
      }

      try {
        $key_file = $this->getKeyFile($key);
        $key_dir = dirname($key_file);
        if (!Filesystem::pathExists($key_dir)) {
          Filesystem::createDirectory(
            $key_dir,
            $mask = 0755,
            $recursive = true);
        }

        $new_file = $key_file.'.new';
        Filesystem::writeFile($new_file, serialize($dict));
        Filesystem::rename($new_file, $key_file);
      } catch (FilesystemException $ex) {
        phlog($ex);
      }
    }

    $this->unlockCache();

    return $this;
  }


  public function deleteKeys(array $keys) {
    $this->validateKeys($keys);

    $this->lockCache(15);

    foreach ($keys as $key) {
      $path = $this->getKeyFile($key);
      Filesystem::remove($path);

      // If removing this key leaves the directory empty, clean it up. Then
      // clean up any empty parent directories.
      $path = dirname($path);
      do {
        if (!Filesystem::isDescendant($path, $this->getCacheDirectory())) {
          break;
        }
        if (Filesystem::listDirectory($path, true)) {
          break;
        }
        Filesystem::remove($path);
        $path = dirname($path);
      } while (true);
    }

    $this->unlockCache();

    return $this;
  }


  public function destroyCache() {
    Filesystem::remove($this->getCacheDirectory());
    return $this;
  }


/* -(  Cache Storage  )------------------------------------------------------ */


  /**
   * @task storage
   */
  public function setCacheDirectory($directory) {
    $this->cacheDirectory = rtrim($directory, '/').'/';
    return $this;
  }


  /**
   * @task storage
   */
  private function getCacheDirectory() {
    if (!$this->cacheDirectory) {
      throw new PhutilInvalidStateException('setCacheDirectory');
    }
    return $this->cacheDirectory;
  }


  /**
   * @task storage
   */
  private function getKeyFile($key) {
    // Colon is a drive separator on Windows.
    $key = str_replace(':', '_', $key);

    // NOTE: We add ".cache" to each file so we don't get a collision if you
    // set the keys "a" and "a/b". Without ".cache", the file "a" would need
    // to be both a file and a directory.
    return $this->getCacheDirectory().$key.'.cache';
  }


  /**
   * @task storage
   */
  private function validateKeys(array $keys) {
    foreach ($keys as $key) {
      // NOTE: Use of "." is reserved for ".lock", "key.new" and "key.cache".
      // Use of "_" is reserved for converting ":".
      if (!preg_match('@^[a-zA-Z0-9/:-]+$@', $key)) {
        throw new Exception(
          pht(
            "Invalid key '%s': directory caches may only contain letters, ".
            "numbers, hyphen, colon and slash.",
            $key));
      }
    }
  }


  /**
   * @task storage
   */
  private function lockCache($wait = 0) {
    if ($this->lock) {
      throw new Exception(
        pht(
          'Trying to %s with a lock!',
          __FUNCTION__.'()'));
    }

    if (!Filesystem::pathExists($this->getCacheDirectory())) {
      Filesystem::createDirectory($this->getCacheDirectory(), 0755, true);
    }

    $lock = PhutilFileLock::newForPath($this->getCacheDirectory().'.lock');
    $lock->lock($wait);

    $this->lock = $lock;
  }


  /**
   * @task storage
   */
  private function unlockCache() {
    if (!$this->lock) {
      throw new PhutilInvalidStateException('lockCache');
    }

    $this->lock->unlock();
    $this->lock = null;
  }

}
