<?php

final class PhabricatorRepositoryCommitRef {

  private $identifier;
  private $epoch;
  private $branch;
  private $canCloseImmediately;

  public function setIdentifier($identifier) {
    $this->identifier = $identifier;
    return $this;
  }

  public function getIdentifier() {
    return $this->identifier;
  }

  public function setEpoch($epoch) {
    $this->epoch = $epoch;
    return $this;
  }

  public function getEpoch() {
    return $this->epoch;
  }

  public function setBranch($branch) {
    $this->branch = $branch;
    return $this;
  }

  public function getBranch() {
    return $this->branch;
  }

  public function setCanCloseImmediately($can_close_immediately) {
    $this->canCloseImmediately = $can_close_immediately;
    return $this;
  }

  public function getCanCloseImmediately() {
    return $this->canCloseImmediately;
  }

}
