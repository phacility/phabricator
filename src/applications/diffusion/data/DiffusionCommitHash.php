<?php

final class DiffusionCommitHash extends Phobject {

  private $hashType;
  private $hashValue;

  public function setHashValue($hash_value) {
    $this->hashValue = $hash_value;
    return $this;
  }

  public function getHashValue() {
    return $this->hashValue;
  }

  public function setHashType($hash_type) {
    $this->hashType = $hash_type;
    return $this;
  }

  public function getHashType() {
    return $this->hashType;
  }

}
