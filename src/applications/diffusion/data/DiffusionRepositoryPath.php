<?php

final class DiffusionRepositoryPath {

  private $fullPath;
  private $path;
  private $hash;
  private $fileType;
  private $fileSize;
  private $externalURI;

  private $lastModifiedCommit;
  private $lastCommitData;

  public function setFullPath($full_path) {
    $this->fullPath = $full_path;
    return $this;
  }

  public function getFullPath() {
    return $this->fullPath;
  }

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

  final public function setExternalURI($external_uri) {
    $this->externalURI = $external_uri;
    return $this;
  }

  final public function getExternalURI() {
    return $this->externalURI;
  }

}
