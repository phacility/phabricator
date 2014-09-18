<?php

final class PhabricatorConfigTableSchema extends Phobject {

  private $name;
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

  public function setCollation($collation) {
    $this->collation = $collation;
    return $this;
  }

  public function getCollation() {
    return $this->collation;
  }

  public function setName($name) {
    $this->name = $name;
    return $this;
  }

  public function getName() {
    return $this->name;
  }

}
