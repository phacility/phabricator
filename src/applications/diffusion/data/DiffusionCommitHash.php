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

  public static function convertArrayToObjects(array $hashes) {
    $hash_objects = array();
    foreach ($hashes as $hash) {
      $type = $hash[0];
      $hash = $hash[1];
      $hash_objects[] = id(new DiffusionCommitHash())
        ->setHashType($type)
        ->setHashValue($hash);
    }
    return $hash_objects;
  }

  public static function newFromDictionary(array $map) {
    $hash_type = idx($map, 'type');
    $hash_value = idx($map, 'value');

    return id(new self())
      ->setHashType($hash_type)
      ->setHashValue($hash_value);
  }

  public function newDictionary() {
    return array(
      'type' => $this->hashType,
      'value' => $this->hashValue,
    );
  }

}
