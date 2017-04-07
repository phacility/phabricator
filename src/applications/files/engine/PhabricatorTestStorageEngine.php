<?php

/**
 * Test storage engine. Does not actually store files. Used for unit tests.
 */
final class PhabricatorTestStorageEngine
  extends PhabricatorFileStorageEngine {

  private static $storage = array();
  private static $nextHandle = 1;

  public function getEngineIdentifier() {
    return 'unit-test';
  }

  public function getEnginePriority() {
    return 1000;
  }

  public function isTestEngine() {
    return true;
  }

  public function canWriteFiles() {
    return true;
  }

  public function hasFilesizeLimit() {
    return false;
  }

  public function writeFile($data, array $params) {
    AphrontWriteGuard::willWrite();
    self::$storage[self::$nextHandle] = $data;
    return (string)self::$nextHandle++;
  }

  public function readFile($handle) {
    if (isset(self::$storage[$handle])) {
      return self::$storage[$handle];
    }
    throw new Exception(pht("No such file with handle '%s'!", $handle));
  }

  public function deleteFile($handle) {
    AphrontWriteGuard::willWrite();
    unset(self::$storage[$handle]);
  }

  public function tamperWithFile($handle, $data) {
    self::$storage[$handle] = $data;
  }

}
