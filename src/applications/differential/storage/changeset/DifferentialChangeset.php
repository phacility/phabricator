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

class DifferentialChangeset extends DifferentialDAO {

  protected $diffID;
  protected $oldFile;
  protected $fileName;
  protected $awayPaths;
  protected $changeType;
  protected $fileType;
  protected $metadata;
  protected $oldProperties;
  protected $newProperties;
  protected $addLines;
  protected $delLines;

  protected function getConfiguration() {
    return array(
      self::CONFIG_SERIALIZATION => array(
        'metadata'      => self::SERIALIZATION_JSON,
        'oldProperties' => self::SERIALIZATION_JSON,
        'newProperties' => self::SERIALIZATION_JSON,
        'awayPaths'     => self::SERIALIZATION_JSON,
      )) + parent::getConfiguration();
  }

  public function getAffectedLineCount() {
    return $this->getAddLines() + $this->getDelLines();
  }

  public function getFileType() {
    return $this->fileType;
  }

  public function getChangeType() {
    return $this->changeType;
  }

  public function getDisplayFilename() {
    $name = $this->getFilename();
    if ($this->getFileType() == DifferentialChangeType::FILE_DIRECTORY) {
      $name .= '/';
    }
    return $name;
  }

  public function addHunk(DifferentialHunk $hunk) {
    if (!isset($this->_hunks)) {
      $this->_hunks = array();
    }
    $this->_hunks[] = $hunk;

    return $this;
  }

  public function attachHunks(array $hunks) {
    $this->_hunks = $hunks;
    return $this;
  }

  public function getHunks() {
    if (!isset($this->_hunks)) {
      throw new Exception("You must load hunks before accessing them.");
    }
    return $this->_hunks;
  }

  public function loadHunks() {
    if (!$this->getID()) {
      return array();
    }
    return id(new DifferentialHunk())->loadAllWhere(
      'changesetID = %d',
      $this->getID());
  }


  public function delete() {
    $this->openTransaction();
      foreach ($this->loadHunks() as $hunk) {
        $hunk->delete();
      }
      $this->_hunks = array();
    $ret = parent::delete();
    $this->saveTransaction();
    return $ret;
  }

  public function getSortKey() {
    $sort_key = $this->getFilename();
    // Sort files with ".h" in them first, so headers (.h, .hpp) come before
    // implementations (.c, .cpp, .cs).
    $sort_key = str_replace('.h', '.!h', $sort_key);
    return $sort_key;
  }

  public function makeNewFile() {
    $file = array();
    foreach ($this->getHunks() as $hunk) {
      $file[] = $hunk->makeNewFile();
    }
    return implode("\n", $file);
  }

  public function makeOldFile() {
    $file = array();
    foreach ($this->getHunks() as $hunk) {
      $file[] = $hunk->makeOldFile();
    }
    return implode("\n", $file);
  }

}
