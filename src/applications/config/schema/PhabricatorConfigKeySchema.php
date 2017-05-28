<?php

final class PhabricatorConfigKeySchema
  extends PhabricatorConfigStorageSchema {

  const MAX_INNODB_KEY_LENGTH = 767;

  private $columnNames;
  private $unique;
  private $table;
  private $indexType;
  private $property;

  public function setIndexType($index_type) {
    $this->indexType = $index_type;
    return $this;
  }

  public function getIndexType() {
    return $this->indexType;
  }

  public function setProperty($property) {
    $this->property = $property;
    return $this;
  }

  public function getProperty() {
    return $this->property;
  }

  public function setUnique($unique) {
    $this->unique = $unique;
    return $this;
  }

  public function getUnique() {
    return $this->unique;
  }

  public function setTable(PhabricatorConfigTableSchema $table) {
    $this->table = $table;
    return $this;
  }

  public function getTable() {
    return $this->table;
  }

  public function setColumnNames(array $column_names) {
    $this->columnNames = array_values($column_names);
    return $this;
  }

  public function getColumnNames() {
    return $this->columnNames;
  }

  protected function getSubschemata() {
    return array();
  }

  public function getKeyColumnAndPrefix($column_name) {
    $matches = null;
    if (preg_match('/^(.*)\((\d+)\)\z/', $column_name, $matches)) {
      return array($matches[1], (int)$matches[2]);
    } else {
      return array($column_name, null);
    }
  }

  public function getKeyByteLength() {
    $size = 0;
    foreach ($this->getColumnNames() as $column_spec) {
      list($column_name, $prefix) = $this->getKeyColumnAndPrefix($column_spec);
      $column = $this->getTable()->getColumn($column_name);
      if (!$column) {
        $size = 0;
        break;
      }
      $size += $column->getKeyByteLength($prefix);
    }

    return $size;
  }

  protected function compareToSimilarSchema(
    PhabricatorConfigStorageSchema $expect) {

    $issues = array();
    if ($this->getColumnNames() !== $expect->getColumnNames()) {
      $issues[] = self::ISSUE_KEYCOLUMNS;
    }

    if ($this->getUnique() !== $expect->getUnique()) {
      $issues[] = self::ISSUE_UNIQUE;
    }

    // A fulltext index can be of any length.
    if ($this->getIndexType() != 'FULLTEXT') {
      if ($this->getKeyByteLength() > self::MAX_INNODB_KEY_LENGTH) {
        $issues[] = self::ISSUE_LONGKEY;
      }
    }

    return $issues;
  }

  public function newEmptyClone() {
    $clone = clone $this;
    $this->table = null;
    return $clone;
  }

}
