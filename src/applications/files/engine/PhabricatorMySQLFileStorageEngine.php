<?php

/*
 * Copyright 2011 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * MySQL blob storage engine. This engine is the easiest to set up but doesn't
 * scale very well.
 *
 * It uses the @{class:PhabricatorFileStorageBlob} to actually access the
 * underlying database table.
 *
 * @task impl     Implementation
 * @task internal Internals
 * @group filestorage
 */
final class PhabricatorMySQLFileStorageEngine
  extends PhabricatorFileStorageEngine {

/* -(  Implementation  )----------------------------------------------------- */


  /**
   * For historical reasons, this engine identifies as "blob".
   *
   * @task impl
   */
  public function getEngineIdentifier() {
    return 'blob';
  }


  /**
   * Write file data into the big blob store table in MySQL. Returns the row
   * ID as the file data handle.
   *
   * @task impl
   */
  public function writeFile($data, array $params) {
    $blob = new PhabricatorFileStorageBlob();
    $blob->setData($data);
    $blob->save();

    return $blob->getID();
  }


  /**
   * Load a stored blob from MySQL.
   * @task impl
   */
  public function readFile($handle) {
    return $this->loadFromMySQLFileStorage($handle)->getData();
  }


  /**
   * Delete a blob from MySQL.
   * @task impl
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
