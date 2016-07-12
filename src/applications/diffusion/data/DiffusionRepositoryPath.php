<?php

final class DiffusionRepositoryPath extends Phobject {

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

  public function setPath($path) {
    $this->path = $path;
    return $this;
  }

  public function getPath() {
    return $this->path;
  }

  public function setHash($hash) {
    $this->hash = $hash;
    return $this;
  }

  public function getHash() {
    return $this->hash;
  }

  public function setLastModifiedCommit(
    PhabricatorRepositoryCommit $commit) {
    $this->lastModifiedCommit = $commit;
    return $this;
  }

  public function getLastModifiedCommit() {
    return $this->lastModifiedCommit;
  }

  public function setLastCommitData(
    PhabricatorRepositoryCommitData $last_commit_data) {
    $this->lastCommitData = $last_commit_data;
    return $this;
  }

  public function getLastCommitData() {
    return $this->lastCommitData;
  }

  public function setFileType($file_type) {
    $this->fileType = $file_type;
    return $this;
  }

  public function getFileType() {
    return $this->fileType;
  }

  public function setFileSize($file_size) {
    $this->fileSize = $file_size;
    return $this;
  }

  public function getFileSize() {
    return $this->fileSize;
  }

  public function setExternalURI($external_uri) {
    $this->externalURI = $external_uri;
    return $this;
  }

  public function getExternalURI() {
    return $this->externalURI;
  }

  public function toDictionary() {
    $last_modified_commit = $this->getLastModifiedCommit();
    if ($last_modified_commit) {
      $last_modified_commit = $last_modified_commit->toDictionary();
    }
    $last_commit_data = $this->getLastCommitData();
    if ($last_commit_data) {
      $last_commit_data = $last_commit_data->toDictionary();
    }
    return array(
      'fullPath' => $this->getFullPath(),
      'path' => $this->getPath(),
      'hash' => $this->getHash(),
      'fileType' => $this->getFileType(),
      'fileSize' => $this->getFileSize(),
      'externalURI' => $this->getExternalURI(),
      'lastModifiedCommit' => $last_modified_commit,
      'lastCommitData' => $last_commit_data,
    );
  }

  public static function newFromDictionary(array $dict) {
    $path = id(new DiffusionRepositoryPath())
      ->setFullPath($dict['fullPath'])
      ->setPath($dict['path'])
      ->setHash($dict['hash'])
      ->setFileType($dict['fileType'])
      ->setFileSize($dict['fileSize'])
      ->setExternalURI($dict['externalURI']);
    if ($dict['lastModifiedCommit']) {
      $last_modified_commit = PhabricatorRepositoryCommit::newFromDictionary(
        $dict['lastModifiedCommit']);
      $path->setLastModifiedCommit($last_modified_commit);
    }
    if ($dict['lastCommitData']) {
      $last_commit_data = PhabricatorRepositoryCommitData::newFromDictionary(
        $dict['lastCommitData']);
      $path->setLastCommitData($last_commit_data);
    }
    return $path;
  }
}
