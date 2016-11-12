<?php

final class PhabricatorConfigServerSchema
  extends PhabricatorConfigStorageSchema {

  private $ref;
  private $databases = array();

  public function setRef(PhabricatorDatabaseRef $ref) {
    $this->ref = $ref;
    return $this;
  }

  public function getRef() {
    return $this->ref;
  }

  public function addDatabase(PhabricatorConfigDatabaseSchema $database) {
    $key = $database->getName();
    if (isset($this->databases[$key])) {
      throw new Exception(
        pht('Trying to add duplicate database "%s"!', $key));
    }
    $this->databases[$key] = $database;
    return $this;
  }

  public function getDatabases() {
    return $this->databases;
  }

  public function getDatabase($key) {
    return idx($this->getDatabases(), $key);
  }

  protected function getSubschemata() {
    return $this->getDatabases();
  }

  protected function compareToSimilarSchema(
    PhabricatorConfigStorageSchema $expect) {
    return array();
  }

  public function newEmptyClone() {
    $clone = clone $this;
    $clone->databases = array();
    return $clone;
  }

}
