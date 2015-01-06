<?php

final class PhabricatorConfigDatabaseSchema
  extends PhabricatorConfigStorageSchema {

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

  protected function getSubschemata() {
    return $this->getTables();
  }

  protected function compareToSimilarSchema(
    PhabricatorConfigStorageSchema $expect) {

    $issues = array();
    if ($this->getCharacterSet() != $expect->getCharacterSet()) {
      $issues[] = self::ISSUE_CHARSET;
    }

    if ($this->getCollation() != $expect->getCollation()) {
      $issues[] = self::ISSUE_COLLATION;
    }

    return $issues;
  }

  public function newEmptyClone() {
    $clone = clone $this;
    $clone->tables = array();
    return $clone;
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

}
