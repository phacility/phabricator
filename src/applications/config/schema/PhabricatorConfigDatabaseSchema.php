<?php

final class PhabricatorConfigDatabaseSchema extends Phobject {

  private $name;
  private $characterSet;
  private $collation;
  private $tables = array();

  public function addTable(PhabricatorConfigTableSchema $table) {
    $key = $table->getName();
    if (isset($this->tables[$key])) {
      throw new Exception(
        pht('Trying to add duplicate table "%s"!', $key));
    }
    $this->tables[$key] = $table;
    return $this;
  }

  public function getTables() {
    return $this->tables;
  }

  public function getTable($key) {
    return idx($this->tables, $key);
  }

  public function isSameSchema(PhabricatorConfigDatabaseSchema $expect) {
    return ($this->toDictionary() === $expect->toDictionary());
  }

  public function toDictionary() {
    return array(
      'name' => $this->getName(),
      'characterSet' => $this->getCharacterSet(),
      'collation' => $this->getCollation(),
    );
  }

  public function setCollation($collation) {
    $this->collation = $collation;
    return $this;
  }

  public function getCollation() {
    return $this->collation;
  }

  public function setCharacterSet($character_set) {
    $this->characterSet = $character_set;
    return $this;
  }

  public function getCharacterSet() {
    return $this->characterSet;
  }

  public function setName($name) {
    $this->name = $name;
    return $this;
  }

  public function getName() {
    return $this->name;
  }

}
