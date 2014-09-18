<?php

final class PhabricatorConfigTableSchema
  extends PhabricatorConfigStorageSchema {

  private $collation;
  private $columns = array();

  public function addColumn(PhabricatorConfigColumnSchema $column) {
    $key = $column->getName();
    if (isset($this->columns[$key])) {
      throw new Exception(
        pht('Trying to add duplicate column "%s"!', $key));
    }
    $this->columns[$key] = $column;
    return $this;
  }

  public function getColumns() {
    return $this->columns;
  }

  public function getColumn($key) {
    return idx($this->getColumns(), $key);
  }

  protected function getSubschemata() {
    return $this->getColumns();
  }

  public function setCollation($collation) {
    $this->collation = $collation;
    return $this;
  }

  public function getCollation() {
    return $this->collation;
  }

  public function compareToSimilarSchema(
    PhabricatorConfigStorageSchema $expect) {

    $issues = array();
    if ($this->getCollation() != $expect->getCollation()) {
      $issues[] = self::ISSUE_COLLATION;
    }

    return $issues;
  }

  public function newEmptyClone() {
    $clone = clone $this;
    $clone->columns = array();
    return $clone;
  }

}
