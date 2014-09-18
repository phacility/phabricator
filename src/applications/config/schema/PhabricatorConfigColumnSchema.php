<?php

final class PhabricatorConfigColumnSchema
  extends PhabricatorConfigStorageSchema {

  private $characterSet;
  private $collation;
  private $columnType;
  private $dataType;

  public function setColumnType($column_type) {
    $this->columnType = $column_type;
    return $this;
  }

  public function getColumnType() {
    return $this->columnType;
  }

  protected function getSubschemata() {
    return array();
  }

  public function setDataType($data_type) {
    $this->dataType = $data_type;
    return $this;
  }

  public function getDataType() {
    return $this->dataType;
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

  public function compareToSimilarSchema(
    PhabricatorConfigStorageSchema $expect) {

    $issues = array();
    if ($this->getCharacterSet() != $expect->getCharacterSet()) {
      $issues[] = self::ISSUE_CHARSET;
    }

    if ($this->getCollation() != $expect->getCollation()) {
      $issues[] = self::ISSUE_COLLATION;
    }

    if ($this->getColumnType() != $expect->getColumnType()) {
      $issues[] = self::ISSUE_COLUMNTYPE;
    }

    return $issues;
  }

  public function newEmptyClone() {
    $clone = clone $this;
    return $clone;
  }

}
