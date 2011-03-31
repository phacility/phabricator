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

final class DiffusionRepositoryPath {

  private $path;
  private $hash;
  private $fileType;
  private $fileSize;

  private $lastModifiedCommit;
  private $lastCommitData;

  final public function setPath($path) {
    $this->path = $path;
    return $this;
  }

  final public function getPath() {
    return $this->path;
  }

  final public function setHash($hash) {
    $this->hash = $hash;
    return $this;
  }

  final public function getHash() {
    return $this->hash;
  }

  final public function setLastModifiedCommit(
    PhabricatorRepositoryCommit $commit) {
    $this->lastModifiedCommit = $commit;
    return $this;
  }

  final public function getLastModifiedCommit() {
    return $this->lastModifiedCommit;
  }

  final public function setLastCommitData(
    PhabricatorRepositoryCommitData $last_commit_data) {
    $this->lastCommitData = $last_commit_data;
    return $this;
  }

  final public function getLastCommitData() {
    return $this->lastCommitData;
  }

  final public function setFileType($file_type) {
    $this->fileType = $file_type;
    return $this;
  }

  final public function getFileType() {
    return $this->fileType;
  }

  final public function setFileSize($file_size) {
    $this->fileSize = $file_size;
    return $this;
  }

  final public function getFileSize() {
    return $this->fileSize;
  }

}
