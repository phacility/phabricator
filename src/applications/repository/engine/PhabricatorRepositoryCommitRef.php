<?php

final class PhabricatorRepositoryCommitRef extends Phobject {

  private $identifier;
  private $epoch;
  private $branch;
  private $isPermanent;
  private $parents = array();

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

  public function setIsPermanent($is_permanent) {
    $this->isPermanent = $is_permanent;
    return $this;
  }

  public function getIsPermanent() {
    return $this->isPermanent;
  }

  public function setParents(array $parents) {
    $this->parents = $parents;
    return $this;
  }

  public function getParents() {
    return $this->parents;
  }

}
