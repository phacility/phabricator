<?php

final class PhabricatorConfigColumnSchema
  extends PhabricatorConfigStorageSchema {

  private $characterSet;
  private $collation;
  private $columnType;
  private $dataType;
  private $nullable;

  public function setNullable($nullable) {
    $this->nullable = $nullable;
    return $this;
  }

  public function getNullable() {
    return $this->nullable;
  }

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

  public function getKeyByteLength($prefix = null) {
    $type = $this->getColumnType();

    $matches = null;
    if (preg_match('/^varchar\((\d+)\)$/', $type, $matches)) {
      // For utf8mb4, each character requires 4 bytes.
      $size = (int)$matches[1];
      if ($prefix && $prefix < $size) {
        $size = $prefix;
      }
      return $size * 4;
    }

    $matches = null;
    if (preg_match('/^char\((\d+)\)$/', $type, $matches)) {
      // We use char() only for fixed-length binary data, so its size
      // is always the column size.
      $size = (int)$matches[1];
      if ($prefix && $prefix < $size) {
        $size = $prefix;
      }
      return $size;
    }

    // The "long..." types are arbitrarily long, so just use a big number to
    // get the point across. In practice, these should always index only a
    // prefix.
    if ($type == 'longtext') {
      $size = (1 << 16);
      if ($prefix && $prefix < $size) {
        $size = $prefix;
      }
      return $size * 4;
    }

    if ($type == 'longblob') {
      $size = (1 << 16);
      if ($prefix && $prefix < $size) {
        $size = $prefix;
      }
      return $size * 1;
    }

    switch ($type) {
      case 'int(10) unsigned':
        return 4;
    }

    // TODO: Build this out to catch overlong indexes.

    return 0;
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

    if ($this->getNullable() !== $expect->getNullable()) {
      $issues[] = self::ISSUE_NULLABLE;
    }

    return $issues;
  }

  public function newEmptyClone() {
    $clone = clone $this;
    return $clone;
  }

}
