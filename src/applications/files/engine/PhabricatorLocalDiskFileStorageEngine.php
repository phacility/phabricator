<?php

/**
 * Local disk storage engine. Keeps files on local disk. This engine is easy
 * to set up, but it doesn't work if you have multiple web frontends!
 *
 * @task internal Internals
 */
final class PhabricatorLocalDiskFileStorageEngine
  extends PhabricatorFileStorageEngine {


/* -(  Engine Metadata  )---------------------------------------------------- */


  /**
   * This engine identifies as "local-disk".
   */
  public function getEngineIdentifier() {
    return 'local-disk';
  }

  public function getEnginePriority() {
    return 5;
  }

  public function canWriteFiles() {
    $path = PhabricatorEnv::getEnvConfig('storage.local-disk.path');
    return (bool)strlen($path);
  }


/* -(  Managing File Data  )------------------------------------------------- */


  /**
   * Write the file data to local disk. Returns the relative path as the
   * file data handle.
   * @task impl
   */
  public function writeFile($data, array $params) {
    $root = $this->getLocalDiskFileStorageRoot();

    // Generate a random, unique file path like "ab/29/1f918a9ac39201ff". We
    // put a couple of subdirectories up front to avoid a situation where we
    // have one directory with a zillion files in it, since this is generally
    // bad news.
    do {
      $name = md5(mt_rand());
      $name = preg_replace('/^(..)(..)(.*)$/', '\\1/\\2/\\3', $name);
      if (!Filesystem::pathExists($root.'/'.$name)) {
        break;
      }
    } while (true);

    $parent = $root.'/'.dirname($name);
    if (!Filesystem::pathExists($parent)) {
      execx('mkdir -p %s', $parent);
    }

    AphrontWriteGuard::willWrite();
    Filesystem::writeFile($root.'/'.$name, $data);

    return $name;
  }


  /**
   * Read the file data off local disk.
   * @task impl
   */
  public function readFile($handle) {
    $path = $this->getLocalDiskFileStorageFullPath($handle);
    return Filesystem::readFile($path);
  }


  /**
   * Deletes the file from local disk, if it exists.
   * @task impl
   */
  public function deleteFile($handle) {
    $path = $this->getLocalDiskFileStorageFullPath($handle);
    if (Filesystem::pathExists($path)) {
      AphrontWriteGuard::willWrite();
      Filesystem::remove($path);
    }
  }


/* -(  Internals  )---------------------------------------------------------- */


  /**
   * Get the configured local disk path for file storage.
   *
   * @return string Absolute path to somewhere that files can be stored.
   * @task internal
   */
  private function getLocalDiskFileStorageRoot() {
    $root = PhabricatorEnv::getEnvConfig('storage.local-disk.path');

    if (!$root || $root == '/' || $root[0] != '/') {
      throw new PhabricatorFileStorageConfigurationException(
        pht(
          "Malformed local disk storage root. You must provide an absolute ".
          "path, and can not use '%s' as the root.",
          '/'));
    }

    return rtrim($root, '/');
  }


  /**
   * Convert a handle into an absolute local disk path.
   *
   * @param string File data handle.
   * @return string Absolute path to the corresponding file.
   * @task internal
   */
  private function getLocalDiskFileStorageFullPath($handle) {
    // Make sure there's no funny business going on here. Users normally have
    // no ability to affect the content of handles, but double-check that
    // we're only accessing local storage just in case.
    if (!preg_match('@^[a-f0-9]{2}/[a-f0-9]{2}/[a-f0-9]{28}\z@', $handle)) {
      throw new Exception(
        pht(
          "Local disk filesystem handle '%s' is malformed!",
          $handle));
    }
    $root = $this->getLocalDiskFileStorageRoot();
    return $root.'/'.$handle;
  }

}
