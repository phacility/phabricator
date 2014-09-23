<?php

final class PhabricatorConfigKeySchema
  extends PhabricatorConfigStorageSchema {

  private $columnNames;
  private $unique;

  public function setUnique($unique) {
    $this->unique = $unique;
    return $this;
  }

  public function getUnique() {
    return $this->unique;
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

  public function compareToSimilarSchema(
    PhabricatorConfigStorageSchema $expect) {

    $issues = array();
    if ($this->getColumnNames() !== $expect->getColumnNames()) {
      $issues[] = self::ISSUE_KEYCOLUMNS;
    }

    if ($this->getUnique() !== $expect->getUnique()) {
      $issues[] = self::ISSUE_UNIQUE;
    }

    return $issues;
  }

  public function newEmptyClone() {
    $clone = clone $this;
    return $clone;
  }

}
