<?php

final class PhabricatorRepositoryParsedChange extends Phobject {

  private $pathID;
  private $targetPathID;
  private $targetCommitID;
  private $changeType;
  private $fileType;
  private $isDirect;
  private $commitSequence;

  public function setPathID($path_id) {
    $this->pathID = $path_id;
    return $this;
  }

  public function getPathID() {
    return $this->pathID;
  }

  public function setCommitSequence($commit_sequence) {
    $this->commitSequence = $commit_sequence;
    return $this;
  }

  public function getCommitSequence() {
    return $this->commitSequence;
  }

  public function setIsDirect($is_direct) {
    $this->isDirect = $is_direct;
    return $this;
  }

  public function getIsDirect() {
    return $this->isDirect;
  }

  public function setFileType($file_type) {
    $this->fileType = $file_type;
    return $this;
  }

  public function getFileType() {
    return $this->fileType;
  }

  public function setChangeType($change_type) {
    $this->changeType = $change_type;
    return $this;
  }

  public function getChangeType() {
    return $this->changeType;
  }

  public function setTargetCommitID($target_commit_id) {
    $this->targetCommitID = $target_commit_id;
    return $this;
  }

  public function getTargetCommitID() {
    return $this->targetCommitID;
  }


  public function setTargetPathID($target_path_id) {
    $this->targetPathID = $target_path_id;
    return $this;
  }

  public function getTargetPathID() {
    return $this->targetPathID;
  }

}
