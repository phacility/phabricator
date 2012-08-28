<?php

/*
 * Copyright 2012 Facebook, Inc.
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
 * Simple blob store DAO for @{class:PhabricatorMySQLFileStorageEngine}.
 *
 * @group filestorage
 */
final class PhabricatorFileStorageBlob extends PhabricatorFileDAO {
  // max_allowed_packet defaults to 1 MiB, escaping can make the data twice
  // longer, query fits in the rest.
  const CHUNK_SIZE = 5e5;

  protected $data;

  private $fullData;

  protected function willWriteData(array &$data) {
    parent::willWriteData($data);

    $this->fullData = $data['data'];
    if (strlen($data['data']) > self::CHUNK_SIZE) {
      $data['data'] = substr($data['data'], 0, self::CHUNK_SIZE);
      $this->openTransaction();
    }
  }

  protected function didWriteData() {
    $size = self::CHUNK_SIZE;
    $length = strlen($this->fullData);
    if ($length > $size) {
      $conn = $this->establishConnection('w');
      for ($offset = $size; $offset < $length; $offset += $size) {
        queryfx(
          $conn,
          'UPDATE %T SET data = CONCAT(data, %s) WHERE %C = %d',
          $this->getTableName(),
          substr($this->fullData, $offset, $size),
          $this->getIDKeyForUse(),
          $this->getID());
      }
      $this->saveTransaction();
    }

    parent::didWriteData();
  }

}
