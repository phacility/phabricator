<?php

final class PhabricatorConfigServerSchema extends Phobject {

  private $databases = array();

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

}
