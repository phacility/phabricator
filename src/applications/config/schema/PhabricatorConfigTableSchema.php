<?php

final class PhabricatorConfigTableSchema
  extends PhabricatorConfigStorageSchema {

  private $collation;
  private $engine;
  private $columns = array();
  private $keys = array();
  private $persistenceType = self::PERSISTENCE_DATA;

  const PERSISTENCE_DATA = 'data';
  const PERSISTENCE_CACHE = 'cache';
  const PERSISTENCE_INDEX = 'index';

  public function addColumn(PhabricatorConfigColumnSchema $column) {
    $key = $column->getName();
    if (isset($this->columns[$key])) {
      throw new Exception(
        pht('Trying to add duplicate column "%s"!', $key));
    }
    $this->columns[$key] = $column;
    return $this;
  }

  public function addKey(PhabricatorConfigKeySchema $key) {
    $name = $key->getName();
    if (isset($this->keys[$name])) {
      throw new Exception(
        pht('Trying to add duplicate key "%s"!', $name));
    }
    $key->setTable($this);
    $this->keys[$name] = $key;
    return $this;
  }

  public function getColumns() {
    return $this->columns;
  }

  public function getColumn($key) {
    return idx($this->getColumns(), $key);
  }

  public function getKeys() {
    return $this->keys;
  }

  public function getKey($key) {
    return idx($this->getKeys(), $key);
  }

  public function setPersistenceType($persistence_type) {
    $this->persistenceType = $persistence_type;
    return $this;
  }

  public function getPersistenceType() {
    return $this->persistenceType;
  }

  public function getPersistenceTypeDisplayName() {
    $map = array(
      self::PERSISTENCE_DATA => pht('Data'),
      self::PERSISTENCE_CACHE => pht('Cache'),
      self::PERSISTENCE_INDEX => pht('Index'),
    );

    $type = $this->getPersistenceType();

    return idx($map, $type, $type);
  }

  protected function getSubschemata() {
    // NOTE: Keys and columns may have the same name, so make sure we return
    // everything.

    return array_merge(
      array_values($this->columns),
      array_values($this->keys));
  }

  public function setCollation($collation) {
    $this->collation = $collation;
    return $this;
  }

  public function getCollation() {
    return $this->collation;
  }

  public function setEngine($engine) {
    $this->engine = $engine;
    return $this;
  }

  public function getEngine() {
    return $this->engine;
  }

  protected function compareToSimilarSchema(
    PhabricatorConfigStorageSchema $expect) {

    $issues = array();
    if ($this->getCollation() != $expect->getCollation()) {
      $issues[] = self::ISSUE_COLLATION;
    }

    if ($this->getEngine() != $expect->getEngine()) {
      $issues[] = self::ISSUE_ENGINE;
    }

    return $issues;
  }

  public function newEmptyClone() {
    $clone = clone $this;
    $clone->columns = array();
    $clone->keys = array();
    return $clone;
  }

}
