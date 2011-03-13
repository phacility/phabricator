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

final class DiffusionPathChange {

  private $path;
  private $commitIdentifier;
  private $commit;
  private $commitData;

  private $changeType;
  private $fileType;
  private $targetPath;

  final public function setPath($path) {
    $this->path = $path;
    return $this;
  }

  final public function getPath() {
    return $this->path;
  }

  public function setChangeType($change_type) {
    $this->changeType = $change_type;
    return $this;
  }

  public function getChangeType() {
    return $this->changeType;
  }

  public function setFileType($file_type) {
    $this->fileType = $file_type;
    return $this;
  }

  public function getFileType() {
    return $this->fileType;
  }

  public function setTargetPath($target_path) {
    $this->targetPath = $target_path;
    return $this;
  }

  public function getTargetPath() {
    return $this->targetPath;
  }

  final public function setCommitIdentifier($commit) {
    $this->commitIdentifier = $commit;
    return $this;
  }

  final public function getCommitIdentifier() {
    return $this->commitIdentifier;
  }

  final public function setCommit($commit) {
    $this->commit = $commit;
    return $this;
  }

  final public function getCommit() {
    return $this->commit;
  }

  final public function setCommitData($commit_data) {
    $this->commitData = $commit_data;
    return $this;
  }

  final public function getCommitData() {
    return $this->commitData;
  }


  final public function getEpoch() {
    if ($this->getCommit()) {
      return $this->getCommit()->getEpoch();
    }
    return null;
  }

  final public function getAuthorName() {
    if ($this->getCommitData()) {
      return $this->getCommitData()->getAuthorName();
    }
    return null;
  }

  final public function getSummary() {
    if (!$this->getCommitData()) {
      return null;
    }
    $message = $this->getCommitData()->getCommitMessage();
    $first = idx(explode("\n", $message), 0);
    return substr($first, 0, 80);
  }




}
