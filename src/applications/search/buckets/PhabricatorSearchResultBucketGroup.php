<?php

final class PhabricatorSearchResultBucketGroup
  extends Phobject {

  private $name;
  private $key;
  private $noDataString;
  private $objects;

  public function setNoDataString($no_data_string) {
    $this->noDataString = $no_data_string;
    return $this;
  }

  public function getNoDataString() {
    return $this->noDataString;
  }

  public function setName($name) {
    $this->name = $name;
    return $this;
  }

  public function getName() {
    return $this->name;
  }

  public function setKey($key) {
    $this->key = $key;
    return $this;
  }

  public function getKey() {
    return $this->key;
  }

  public function setObjects(array $objects) {
    $this->objects = $objects;
    return $this;
  }

  public function getObjects() {
    return $this->objects;
  }

}
