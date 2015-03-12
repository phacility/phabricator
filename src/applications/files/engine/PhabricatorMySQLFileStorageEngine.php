<?php

/**
 * MySQL blob storage engine. This engine is the easiest to set up but doesn't
 * scale very well.
 *
 * It uses the @{class:PhabricatorFileStorageBlob} to actually access the
 * underlying database table.
 *
 * @task internal Internals
 */
final class PhabricatorMySQLFileStorageEngine
  extends PhabricatorFileStorageEngine {


/* -(  Engine Metadata  )---------------------------------------------------- */


  /**
   * For historical reasons, this engine identifies as "blob".
   */
  public function getEngineIdentifier() {
    return 'blob';
  }

  public function getEnginePriority() {
    return 1;
  }

  public function canWriteFiles() {
    return ($this->getFilesizeLimit() > 0);
  }


  public function hasFilesizeLimit() {
    return true;
  }


  public function getFilesizeLimit() {
    return PhabricatorEnv::getEnvConfig('storage.mysql-engine.max-size');
  }


/* -(  Managing File Data  )------------------------------------------------- */


  /**
   * Write file data into the big blob store table in MySQL. Returns the row
   * ID as the file data handle.
   */
  public function writeFile($data, array $params) {
    $blob = new PhabricatorFileStorageBlob();
    $blob->setData($data);
    $blob->save();

    return $blob->getID();
  }


  /**
   * Load a stored blob from MySQL.
   */
  public function readFile($handle) {
    return $this->loadFromMySQLFileStorage($handle)->getData();
  }


  /**
   * Delete a blob from MySQL.
   */
  public function deleteFile($handle) {
    $this->loadFromMySQLFileStorage($handle)->delete();
  }


/* -(  Internals  )---------------------------------------------------------- */


  /**
   * Load the Lisk object that stores the file data for a handle.
   *
   * @param string  File data handle.
   * @return PhabricatorFileStorageBlob Data DAO.
   * @task internal
   */
  private function loadFromMySQLFileStorage($handle) {
    $blob = id(new PhabricatorFileStorageBlob())->load($handle);
    if (!$blob) {
      throw new Exception("Unable to load MySQL blob file '{$handle}'!");
    }
    return $blob;
  }

}
